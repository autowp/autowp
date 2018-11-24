<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Validator;
use Zend\View\Model\JsonModel;

use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

class AclController extends AbstractRestfulController
{
    /**
     * @var string
     */
    private $cacheKey = 'acl_cache_key';

    private $cache;

    /**
     * @var TableGateway
     */
    private $privilegeAllowedTable;

    /**
     * @var TableGateway
     */
    private $privilegeDeniedTable;

    /**
     * @var TableGateway
     */
    private $resourceTable;

    /**
     * @var TableGateway
     */
    private $privilegeTable;

    /**
     * @var TableGateway
     */
    private $roleTable;

    /**
     * @var InputFilter
     */
    private $rolesInputFilter;

    /**
     * @var InputFilter
     */
    private $roleParentsPostFilter;

    /**
     * @var TableGateway
     */
    private $roleParentTable;

    /**
     * @var InputFilter
     */
    private $rolesPostFilter;

    /**
     * @var InputFilter
     */
    private $rulesPostFilter;

    public function __construct(
        $cache,
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

        $this->roleTable = $roleTable;
        $this->roleParentTable = $roleParentTable;

        $this->resourceTable = $resourceTable;
        $this->privilegeTable = $privilegeTable;

        $this->privilegeAllowedTable = $privilegeAllowedTable;
        $this->privilegeDeniedTable = $privilegeDeniedTable;

        $this->roleParentsPostFilter = $roleParentsPostFilter;
        $this->rolesInputFilter = $rolesInputFilter;
        $this->rolesPostFilter = $rolesPostFilter;
        $this->rulesPostFilter = $rulesPostFilter;
    }

    public function isAllowedAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        return new JsonModel([
            'result' => $this->user()->isAllowed(
                $this->params()->fromQuery('resource'),
                $this->params()->fromQuery('privilege')
            )
        ]);
    }

    public function inheritRolesAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $user = $this->user()->get();
        $result = [
            $user->role => true
        ];

        $roles = $this->params()->fromQuery('roles');
        $roles = explode(',', $roles);

        foreach ($roles as $role) {
            $result[$role] = $this->user()->inheritsRole($role);
        }

        return new JsonModel($result);
    }

    private function getRoles($parentId)
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
                'childs' => $this->getRoles($role['id'])
            ];
        }

        return $roles;
    }

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
                    'name' => $role['name']
                ];
            }
        }

        return new JsonModel([
            'items' => $roles
        ]);
    }

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
            'name' => $data['name']
        ]);

        $this->resetCache();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    public function resourcesAction()
    {
        $resources = [];
        foreach ($this->resourceTable->select([]) as $resource) {
            $id = $resource['id'];
            $rows = $this->privilegeTable->select([
                'resource_id' => $id
            ]);

            $privileges = [];
            foreach ($rows as $privilege) {
                $privileges[] = [
                    'name' => $privilege['name']
                ];
            }

            $resources[] = [
                'name'       => $resource['name'],
                'privileges' => $privileges
            ];
        }

        return new JsonModel([
            'items' => $resources
        ]);
    }

    public function rulesAction()
    {
        $rules = [];

        $rows = $this->privilegeAllowedTable->select(function (Sql\Select $select) {
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
                'allowed'   => true
            ];
        }

        $rows = $this->privilegeDeniedTable->select(function (Sql\Select $select) {
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
                'allowed'   => false
            ];
        }

        return new JsonModel([
            'items' => $rules
        ]);
    }

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
            'haystack' => $avaliableRoles
        ]);
        $this->rulesPostFilter->get('role')->getValidatorChain()->attach($validator);

        $avaliableResources = [];
        foreach ($this->resourceTable->select() as $row) {
            $avaliableResources[] = $row['name'];
        }

        $validator = new Validator\InArray([
            'haystack' => $avaliableResources
        ]);
        $this->rulesPostFilter->get('resource')->getValidatorChain()->attach($validator);

        $data = $this->processBodyContent($this->getRequest());
        $this->rulesPostFilter->setData($data);

        if (! $this->rulesPostFilter->isValid()) {
            return $this->inputFilterResponse($this->rulesPostFilter);
        }

        $data = $this->rulesPostFilter->getValues();

        $role = $this->roleTable->select([
            'name' => $data['role']
        ])->current();
        if (! $role) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $resource = $this->resourceTable->select([
            'name' => $data['resource']
        ])->current();
        if (! $resource) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $privilege = $this->privilegeTable->select([
            'name'        => $data['privilege'],
            'resource_id' => $resource['id']
        ])->current();
        if (! $privilege) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $where = [
            'role_id = ?'      => $role['id'],
            'privilege_id = ?' => $privilege['id']
        ];

        $this->privilegeDeniedTable->delete($where);
        $this->privilegeAllowedTable->delete($where);

        if ($data['allowed']) {
            $this->privilegeAllowedTable->insert([
                'privilege_id' => $privilege['id'],
                'role_id'      => $role['id']
            ]);
        } else {
            $this->privilegeDeniedTable->insert([
                'privilege_id' => $privilege['id'],
                'role_id'      => $role['id']
            ]);
        }

        $this->resetCache();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    public function roleAction()
    {
        $role = $this->roleTable->select([
            'name' => $this->params()->fromRoute('role')
        ])->current();

        if (! $role) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        return new JsonModel([
            'name' => $role['name']
        ]);
    }

    public function roleParentsAction()
    {
        $role = $this->roleTable->select([
            'name' => $this->params()->fromRoute('role')
        ])->current();

        if (! $role) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $select = new Sql\Select($this->roleTable->getTable());
        $select->join('acl_roles_parents', 'acl_roles.id = acl_roles_parents.parent_role_id', [])
            ->where([
                'acl_roles_parents.role_id' => $role['id']
            ]);

        $items = [];
        foreach ($this->roleTable->selectWith($select) as $row) {
            $items[] = [
                'name' => $row['name']
            ];
        }

        return new JsonModel([
            'items' => $items
        ]);
    }

    public function roleParentsPostAction()
    {
        if (! $this->user()->isAllowed('rights', 'edit')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $role = $this->roleTable->select([
            'name' => $this->params()->fromRoute('role')
        ])->current();

        if (! $role) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $avaliableRoles = [];
        $rows = $this->roleTable->select([ //TODO: filter already parents
            'name <> ?' => $role['name']
        ]);
        foreach ($rows as $row) {
            $avaliableRoles[] = $row['name'];
        }

        $validator = new Validator\InArray([
            'haystack' => $avaliableRoles
        ]);

        $this->roleParentsPostFilter->get('role')->getValidatorChain()->attach($validator);

        $data = $this->processBodyContent($this->getRequest());
        $this->roleParentsPostFilter->setData($data);

        if (! $this->roleParentsPostFilter->isValid()) {
            return $this->inputFilterResponse($this->roleParentsPostFilter);
        }

        $data = $this->roleParentsPostFilter->getValues();

        $parentRole = $this->roleTable->select([
            'name' => $data['role']
        ])->current();

        if (! $parentRole) {
            return new ApiProblemResponse(new ApiProblem(404, 'Entity not found'));
        }

        $this->roleParentTable->insert([
            'role_id'        => $role['id'],
            'parent_role_id' => $parentRole['id']
        ]);

        $this->resetCache();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }

    private function resetCache()
    {
        $this->cache->removeItem($this->cacheKey);
    }
}
