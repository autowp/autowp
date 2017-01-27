<?php

namespace Application\Controller\Moder;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

class RightsController extends AbstractActionController
{
    private $acl;

    private $cache;

    /**
     * @var string
     */
    private $cacheKey = 'acl_cache_key';

    /**
     * @var Form
     */
    private $roleForm;

    /**
     * @var Form
     */
    private $ruleForm;

    /**
     * @var Form
     */
    private $roleParentForm;

    /**
     * @var TableGateway
     */
    private $roleTable;

    /**
     * @var TableGateway
     */
    private $roleParentTable;

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
    private $privilegeAllowedTable;

    /**
     * @var TableGateway
     */
    private $privilegeDeniedTable;

    public function __construct(
        $acl,
        $cache,
        Form $roleForm,
        Form $ruleForm,
        Form $roleParentForm,
        Adapter $adapter
    ) {
        $this->acl = $acl;
        $this->cache = $cache;

        $this->roleForm = $roleForm;
        $this->ruleForm = $ruleForm;
        $this->roleParentForm = $roleParentForm;

        $this->roleTable = new TableGateway('acl_roles', $adapter);
        $this->roleParentTable = new TableGateway('acl_roles_parents', $adapter);
        $this->resourceTable = new TableGateway('acl_resources', $adapter);
        $this->privilegeTable = new TableGateway('acl_resources_privileges', $adapter);
        $this->privilegeAllowedTable = new TableGateway('acl_roles_privileges_allowed', $adapter);
        $this->privilegeDeniedTable = new TableGateway('acl_roles_privileges_denied', $adapter);
    }

    private function resetCache()
    {
        $this->cache->removeItem($this->cacheKey);
    }

    public function resetAction()
    {
        if (! $this->user()->isAllowed('rights', 'edit')) {
            return $this->forbiddenAction();
        }

        $this->resetCache();

        return $this->redirect()->toRoute('moder/rights');
    }

    public function indexAction()
    {
        if (! $this->user()->isAllowed('rights', 'edit')) {
            return $this->forbiddenAction();
        }

        $rows = $this->roleTable->select(function (Sql\Select $select) {
            $select
                ->columns(['id', 'name'])
                ->order('name');
        });
        $roleOptions = [];
        foreach ($rows as $row) {
            $roleOptions[$row['id']] = $row['name'];
        }

        $resourceOptions = [];
        foreach ($this->resourceTable->select([]) as $resource) {
            $id = $resource['id'];
            $rows = $this->privilegeTable->select([
                'resource_id' => $id
            ]);

            foreach ($rows as $privilege) {
                $resourceOptions[$id] = $resource['name'] . ' / ' . $privilege['name'];
            }
        }

        $this->roleForm->setAttribute(
            'action',
            $this->url()->fromRoute('moder/rights/params', [
                'form' => 'add-role'
            ])
        );
        $this->roleForm->get('parent_role_id')->setValueOptions($roleOptions);

        $this->ruleForm->setAttribute(
            'action',
            $this->url()->fromRoute('moder/rights/params', [
                'form' => 'add-rule'
            ])
        );
        $this->ruleForm->get('role_id')->setValueOptions($roleOptions);
        $this->ruleForm->get('privilege_id')->setValueOptions($resourceOptions);

        $this->roleParentForm->setAttribute(
            'action',
            $this->url()->fromRoute('moder/rights/params', [
                'form' => 'add-role-parent'
            ])
        );
        $this->roleParentForm->get('role_id')->setValueOptions($roleOptions);
        $this->roleParentForm->get('parent_role_id')->setValueOptions($roleOptions);

        if ($this->getRequest()->isPost()) {
            switch ($this->params('form')) {
                case 'add-role':
                    $this->roleForm->setData($this->params()->fromPost());
                    if ($this->roleForm->isValid()) {
                        $data = $this->roleForm->getData();

                        $this->roleTable->insert([
                            'name' => $data['role']
                        ]);
                        $id = $this->roleTable->getLastInsertValue();

                        $parentRole = $this->roleTable->select([
                            'id' => (int)$data['parent_role_id']
                        ])->current();

                        if ($parentRole) {
                            $this->roleParentTable->insert([
                                'role_id'        => $id,
                                'parent_role_id' => $parentRole['id']
                            ]);
                        }

                        $this->resetCache();

                        return $this->redirect()->toRoute('moder/rights');
                    }
                    break;

                case 'add-role-parent':
                    $this->roleParentForm->setData($this->params()->fromPost());
                    if ($this->roleParentForm->isValid()) {
                        $data = $this->roleParentForm->getData();

                        if ($data['role_id'] != $data['parent_role_id']) {
                            $this->roleParentTable->insert([
                                'role_id'        => $data['role_id'],
                                'parent_role_id' => $data['parent_role_id']
                            ]);
                        }

                        $this->resetCache();

                        return $this->redirect()->toRoute('moder/rights');
                    }
                    break;

                case 'add-rule':
                    $this->ruleForm->setData($this->params()->fromPost());
                    if ($this->ruleForm->isValid()) {
                        $data = $this->ruleForm->getData();

                        $where = [
                            'role_id = ?'      => $data['role_id'],
                            'privilege_id = ?' => $data['privilege_id']
                        ];

                        $this->privilegeDeniedTable->delete($where);
                        $this->privilegeAllowedTable->delete($where);

                        if ($data['what']) {
                            unset($data['what']);
                            $this->privilegeAllowedTable->insert($data);
                        } else {
                            unset($data['what']);
                            $this->privilegeDeniedTable->insert($data);
                        }

                        $this->resetCache();

                        return $this->redirect()->toRoute('moder/rights');
                    }
                    break;
            }
        }

        $rules = [];

        $rows = $this->privilegeAllowedTable->select(function (Sql\Select $select) {
            $select
                ->columns(['allowed' => new Sql\Expression('1')])
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
            $rules[] = $row;
        }

        $rows = $this->privilegeDeniedTable->select(function (Sql\Select $select) {
            $select
                ->columns(['allowed' => new Sql\Expression('0')])
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
            $rules[] = $row;
        }

        $roles = [];
        foreach ($this->roleTable->select([]) as $role) {
            $roles[] = $role;
        }

        $resources = [];
        foreach ($this->resourceTable->select([]) as $resource) {
            $id = $resource['id'];
            $rows = $this->privilegeTable->select([
                'resource_id' => $id
            ]);

            $privileges = [];
            foreach ($rows as $privilege) {
                $privileges[] = [
                    'id'   => $privilege['id'],
                    'name' => $privilege['name']
                ];
            }

            $resources[] = [
                'id'         => $resource['id'],
                'name'       => $resource['name'],
                'privileges' => $privileges
            ];
        }

        return [
            'acl'               => $this->acl,
            'addRuleForm'       => $this->ruleForm,
            'addRoleForm'       => $this->roleForm,
            'addRoleParentForm' => $this->roleParentForm,
            'resources'         => $resources,
            'roles'             => $roles,
            'rolesTree'         => $this->getRoles(null),
            'rules'             => $rules
        ];
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
                'id'     => $role['id'],
                'name'   => $role['name'],
                'childs' => $this->getRoles($role['id'])
            ];
        }

        return $roles;
    }
}
