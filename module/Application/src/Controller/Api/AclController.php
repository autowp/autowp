<?php

namespace Application\Controller\Api;

use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\Validator;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function explode;

/**
 * @method User user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 */
class AclController extends AbstractRestfulController
{
    private string $cacheKey = 'acl_cache_key';

    private StorageInterface $cache;

    private TableGateway $privilegeAllowedTable;

    private TableGateway $privilegeDeniedTable;

    private TableGateway $resourceTable;

    private TableGateway $privilegeTable;

    private TableGateway $roleTable;

    private InputFilter $rolesInputFilter;

    private InputFilter $roleParentsPostFilter;

    private TableGateway $roleParentTable;

    private InputFilter $rolesPostFilter;

    private InputFilter $rulesPostFilter;

    public function __construct(
        StorageInterface $cache,
        InputFilter $rolesInputFilter,
        InputFilter $rolesPostFilter,
        InputFilter $roleParentsPostFilter,
        InputFilter $rulesPostFilter,
        TableGateway $roleTable,
        TableGateway $roleParentTable,
        TableGateway $resourceTable,
        TableGateway $privilegeTable,
        TableGateway $privilegeAllowedTable,
        TableGateway $privilegeDeniedTable
    ) {
        $this->cache = $cache;

        $this->roleTable       = $roleTable;
        $this->roleParentTable = $roleParentTable;

        $this->resourceTable  = $resourceTable;
        $this->privilegeTable = $privilegeTable;

        $this->privilegeAllowedTable = $privilegeAllowedTable;
        $this->privilegeDeniedTable  = $privilegeDeniedTable;

        $this->roleParentsPostFilter = $roleParentsPostFilter;
        $this->rolesInputFilter      = $rolesInputFilter;
        $this->rolesPostFilter       = $rolesPostFilter;
        $this->rulesPostFilter       = $rulesPostFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function isAllowedAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        return new JsonModel([
            'result' => $this->user()->isAllowed( // @phan-suppress-current-line PhanUndeclaredMethod
                $this->params()->fromQuery('resource'),
                $this->params()->fromQuery('privilege')
            ),
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function inheritRolesAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $user   = $this->user()->get();
        $result = [
            $user['role'] => true,
        ];

        $roles = $this->params()->fromQuery('roles');
        $roles = explode(',', $roles);

        foreach ($roles as $role) {
            $result[$role] = $this->user()->inheritsRole($role);
        }

        return new JsonModel($result);
    }

    private function getRoles(?int $parentId): array
    {
        $select = new Sql\Select($this->roleTable->getTable());

        if ($parentId) {
            $select
                ->join('acl_roles_parents', 'acl_roles.id = acl_roles_parents.role_id', [])
                ->where(['acl_roles_parents.parent_role_id = ?' => $parentId]);
        } else {
            $select
                ->join('acl_roles_parents', 'acl_roles.id = acl_roles_parents.role_id', [], $select::JOIN_LEFT)
                ->where('acl_roles_parents.role_id IS NULL');
        }

        $roles = [];
        foreach ($this->roleTable->selectWith($select) as $role) {
            $roles[] = [
                'name'   => $role['name'],
                'childs' => $this->getRoles($role['id']),
            ];
        }

        return $roles;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function rolesAction()
    {
        $this->rolesInputFilter->setData($this->params()->fromQuery());

        if (! $this->rolesInputFilter->isValid()) {
            return $this->inputFilterResponse($this->rolesInputFilter);
        }

        $data = $this->rolesInputFilter->getValues();

        if ($data['recursive']) {
            $roles = $this->getRoles(null);
        } else {
            $roles = [];
            foreach ($this->roleTable->select([]) as $role) {
                $roles[] = [
                    'name' => $role['name'],
                ];
            }
        }

        return new JsonModel([
            'items' => $roles,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function rolesPostAction()
    {
        if (! $this->user()->isAllowed('rights', 'edit')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $data = $this->processBodyContent($this->getRequest());
        $this->rolesPostFilter->setData($data);

        if (! $this->rolesPostFilter->isValid()) {
            return $this->inputFilterResponse($this->rolesPostFilter);
        }

        $data = $this->rolesPostFilter->getValues();

        $this->roleTable->insert([
            'name' => $data['name'],
        ]);

        $this->resetCache();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    public function resourcesAction(): JsonModel
    {
        $resources = [];
        foreach ($this->resourceTable->select([]) as $resource) {
            $id   = $resource['id'];
            $rows = $this->privilegeTable->select([
                'resource_id' => $id,
            ]);

            $privileges = [];
            foreach ($rows as $privilege) {
                $privileges[] = [
                    'name' => $privilege['name'],
                ];
            }

            $resources[] = [
                'name'       => $resource['name'],
                'privileges' => $privileges,
            ];
        }

        return new JsonModel([
            'items' => $resources,
        ]);
    }

    public function rulesAction(): JsonModel
    {
        $rules = [];

        $rows = $this->privilegeAllowedTable->select(function (Sql\Select $select): void {
            $select
                ->join(
                    'acl_resources_privileges',
                    'acl_roles_privileges_allowed.privilege_id = acl_resources_privileges.id',
                    ['privilege' => 'name']
                )
                ->join(
                    'acl_resources',
                    'acl_resources_privileges.resource_id = acl_resources.id',
                    ['resource' => 'name']
                )
                ->join(
                    'acl_roles',
                    'acl_roles_privileges_allowed.role_id = acl_roles.id',
                    ['role' => 'name']
                );
        });
        foreach ($rows as $row) {
            $rules[] = [
                'resource'  => $row['resource'],
                'privilege' => $row['privilege'],
                'role'      => $row['role'],
                'allowed'   => true,
            ];
        }

        $rows = $this->privilegeDeniedTable->select(function (Sql\Select $select): void {
            $select
                ->join(
                    'acl_resources_privileges',
                    'acl_roles_privileges_denied.privilege_id = acl_resources_privileges.id',
                    ['privilege' => 'name']
                )
                ->join(
                    'acl_resources',
                    'acl_resources_privileges.resource_id = acl_resources.id',
                    ['resource' => 'name']
                )
                ->join(
                    'acl_roles',
                    'acl_roles_privileges_denied.role_id = acl_roles.id',
                    ['role' => 'name']
                );
        });
        foreach ($rows as $row) {
            $rules[] = [
                'resource'  => $row['resource'],
                'privilege' => $row['privilege'],
                'role'      => $row['role'],
                'allowed'   => false,
            ];
        }

        return new JsonModel([
            'items' => $rules,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function rulesPostAction()
    {
        if (! $this->user()->isAllowed('rights', 'edit')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $avaliableRoles = [];
        foreach ($this->roleTable->select() as $row) {
            $avaliableRoles[] = $row['name'];
        }

        $validator = new Validator\InArray([
            'haystack' => $avaliableRoles,
        ]);
        $this->rulesPostFilter->get('role')->getValidatorChain()->attach($validator);

        $avaliableResources = [];
        foreach ($this->resourceTable->select() as $row) {
            $avaliableResources[] = $row['name'];
        }

        $validator = new Validator\InArray([
            'haystack' => $avaliableResources,
        ]);
        $this->rulesPostFilter->get('resource')->getValidatorChain()->attach($validator);

        $data = $this->processBodyContent($this->getRequest());
        $this->rulesPostFilter->setData($data);

        if (! $this->rulesPostFilter->isValid()) {
            return $this->inputFilterResponse($this->rulesPostFilter);
        }

        $data = $this->rulesPostFilter->getValues();

        $role = $this->roleTable->select([
            'name' => $data['role'],
        ])->current();
        if (! $role) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $resource = $this->resourceTable->select([
            'name' => $data['resource'],
        ])->current();
        if (! $resource) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $privilege = $this->privilegeTable->select([
            'name'        => $data['privilege'],
            'resource_id' => $resource['id'],
        ])->current();
        if (! $privilege) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $where = [
            'role_id = ?'      => $role['id'],
            'privilege_id = ?' => $privilege['id'],
        ];

        $this->privilegeDeniedTable->delete($where);
        $this->privilegeAllowedTable->delete($where);

        if ($data['allowed']) {
            $this->privilegeAllowedTable->insert([
                'privilege_id' => $privilege['id'],
                'role_id'      => $role['id'],
            ]);
        } else {
            $this->privilegeDeniedTable->insert([
                'privilege_id' => $privilege['id'],
                'role_id'      => $role['id'],
            ]);
        }

        $this->resetCache();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function roleAction()
    {
        $role = $this->roleTable->select([
            'name' => $this->params()->fromRoute('role'),
        ])->current();

        if (! $role) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        return new JsonModel([
            'name' => $role['name'],
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function roleParentsAction()
    {
        $role = $this->roleTable->select([
            'name' => $this->params()->fromRoute('role'),
        ])->current();

        if (! $role) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $select = new Sql\Select($this->roleTable->getTable());
        $select->join('acl_roles_parents', 'acl_roles.id = acl_roles_parents.parent_role_id', [])
            ->where([
                'acl_roles_parents.role_id' => $role['id'],
            ]);

        $items = [];
        foreach ($this->roleTable->selectWith($select) as $row) {
            $items[] = [
                'name' => $row['name'],
            ];
        }

        return new JsonModel([
            'items' => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function roleParentsPostAction()
    {
        if (! $this->user()->isAllowed('rights', 'edit')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $role = $this->roleTable->select([
            'name' => $this->params()->fromRoute('role'),
        ])->current();

        if (! $role) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $avaliableRoles = [];
        $rows           = $this->roleTable->select([ //TODO: filter already parents
            'name <> ?' => $role['name'],
        ]);
        foreach ($rows as $row) {
            $avaliableRoles[] = $row['name'];
        }

        $validator = new Validator\InArray([
            'haystack' => $avaliableRoles,
        ]);

        $this->roleParentsPostFilter->get('role')->getValidatorChain()->attach($validator);

        $data = $this->processBodyContent($this->getRequest());
        $this->roleParentsPostFilter->setData($data);

        if (! $this->roleParentsPostFilter->isValid()) {
            return $this->inputFilterResponse($this->roleParentsPostFilter);
        }

        $data = $this->roleParentsPostFilter->getValues();

        $parentRole = $this->roleTable->select([
            'name' => $data['role'],
        ])->current();

        if (! $parentRole) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $this->roleParentTable->insert([
            'role_id'        => $role['id'],
            'parent_role_id' => $parentRole['id'],
        ]);

        $this->resetCache();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    private function resetCache(): void
    {
        $this->cache->removeItem($this->cacheKey);
    }
}
