<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\Acl\Role;
use Application\Model\DbTable\Acl\Resource;
use Application\Model\DbTable\Acl\ResourcePrivilege;
use Application\Model\DbTable\Acl\RoleParent;
use Application\Model\DbTable\Acl\RolePrivilegeAllowed;
use Application\Model\DbTable\Acl\RolePrivilegeDenied;

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

    public function __construct($acl, $cache, Form $roleForm, Form $ruleForm, Form $roleParentForm)
    {
        $this->acl = $acl;
        $this->cache = $cache;

        $this->roleForm = $roleForm;
        $this->ruleForm = $ruleForm;
        $this->roleParentForm = $roleParentForm;
    }

    private function resetCache()
    {
        $this->cache->removeItem($this->cacheKey);
    }

    public function resetAction()
    {
        if (!$this->user()->isAllowed('rights', 'edit')) {
            return $this->forbiddenAction();
        }

        $this->resetCache();

        return $this->redirect()->toUrl(
            $this->url()->fromRoute('moder/rights')
        );
    }

    public function indexAction()
    {
        if (!$this->user()->isAllowed('rights', 'edit')) {
            return $this->forbiddenAction();
        }

        $roles = new Role();

        $resources = new Resource();

        $db = $roles->getAdapter();
        $roleOptions = $db->fetchPairs(
            $db->select()
                ->from($roles->info('name'), ['id', 'name'])
                ->order('name')
        );

        $resourceOptions = [];
        foreach ($resources->fetchAll() as $resource) {
            foreach ($resource->findfindDependentRowset(ResourcePrivilege::class) as $privilege) {
                $resourceOptions[$privilege->id] = $resource->name . ' / ' . $privilege->name;
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
            switch ($this->params('form'))
            {
                case 'add-role':
                    $this->roleForm->setData($this->params()->fromPost());
                    if ($this->roleForm->isValid()) {
                        $data = $this->roleForm->getData();

                        $id = $roles->insert([
                            'name' => $data['role']
                        ]);

                        $parent_role = $roles->find($data['parent_role_id'])->current();

                        if ($parent_role) {
                            $roles_parents = new RoleParent();

                            $roles_parents->insert([
                                'role_id'        => $id,
                                'parent_role_id' => $parent_role->id
                            ]);
                        }

                        $this->resetCache();

                        return $this->redirect()->toUrl(
                            $this->url()->fromRoute('moder/rights')
                        );
                    }
                    break;

                case 'add-role-parent':
                    $this->roleParentForm->setData($this->params()->fromPost());
                    if ($this->roleParentForm->isValid()) {
                        $data = $this->roleParentForm->getData();

                        if ($data['role_id'] != $data['parent_role_id']) {
                            $roles_parents = new RoleParent();

                            $roles_parents->insert([
                                'role_id' => $data['role_id'],
                                'parent_role_id' => $data['parent_role_id']
                            ]);
                        }

                        $this->resetCache();

                        return $this->redirect()->toUrl(
                            $this->url()->fromRoute('moder/rights')
                        );
                    }
                    break;

                case 'add-rule':
                    $this->ruleForm->setData($this->params()->fromPost());
                    if ($this->ruleForm->isValid()) {
                        $data = $this->ruleForm->getData();

                        $allowed = new RolePrivilegeAllowed();
                        $denied = new RolePrivilegeDenied();

                        $db = $allowed->getAdapter();
                        $where = [
                            $db->quoteInto('role_id = ?', $data['role_id']),
                            $db->quoteInto('privilege_id = ?', $data['privilege_id'])
                        ];

                        $denied->delete($where);
                        $allowed->delete($where);

                        if ($data['what']) {
                            unset($data['what']);
                            $allowed->insert($data);

                        } else {
                            unset($data['what']);
                            $denied->insert($data);
                        }

                        $this->resetCache();

                        return $this->redirect()->toUrl(
                            $this->url()->fromRoute('moder/rights')
                        );
                    }
                    break;
            }
        }

        $rules = [];

        $arpaTable = new RolePrivilegeAllowed();
        foreach ($arpaTable->fetchAll() as $row) {
            $privilege = $row->findParentRow(ResourcePrivilege::class);
            $rules[] = [
                'allowed'   => true,
                'role'      => $row->findParentRow(Role::class)->name,
                'privilege' => $privilege->name,
                'resource'  => $privilege->findParentRow(Resource::class)->name
            ];
        }

        $arpdTable = new RolePrivilegeDenied();
        foreach ($arpdTable->fetchAll() as $row) {
            $privilege = $row->findParentRow(ResourcePrivilege::class);
            $rules[] = [
                'allowed'   => false,
                'role'      => $row->findParentRow(Role::class)->name,
                'privilege' => $privilege->name,
                'resource'  => $privilege->findParentRow(Resource::class)->name
            ];
        }

        return [
            'acl'               => $this->acl,
            'addRuleForm'       => $this->ruleForm,
            'addRoleForm'       => $this->roleForm,
            'addRoleParentForm' => $this->roleParentForm,
            'resources'         => $resources->fetchAll(),
            'privileges'        => new ResourcePrivilege(),
            'roles'             => $roles->fetchAll(),
            'rules'             => $rules
        ];
    }
}