<?php

class Moder_RightsController extends Zend_Controller_Action
{
    private function resetCache()
    {
        $cache = $this->getInvokeArg('bootstrap')->getResource('cachemanager')->getCache('long');
        $cache->remove('Project_Application_Resource_Acl');
    }

    public function resetAction()
    {
        $this->resetCache();

        return $this->_redirect(
            $this->_helper->url->url(array(
                'action' => 'index'
            ))
        );
    }

    public function indexAction()
    {
        if (!$this->_helper->user()->isAllowed('rights', 'edit')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $roles = new Acl_Roles();

        $resources = new Acl_Resources();

        $addRoleForm = new Application_Form_Moder_Acl_Role_Add(array(
            'action' => $this->_helper->url->url(array('form' => 'add-role'))
        ));

        $addRuleForm = new Application_Form_Moder_Acl_Rule(array(
            'action' => $this->_helper->url->url(array('form' => 'add-rule'))
        ));

        $addRoleParentForm = new Application_Form_Moder_Acl_Role_Parent_Add(array(
            'action' => $this->_helper->url->url(array('form' => 'add-role-parent'))
        ));

        if ($this->getRequest()->isPost()) {
            switch ($this->_getParam('form'))
            {
                case 'add-role':
                    if ($addRoleForm->isValid($this->getRequest()->getPost())) {
                        $data = $addRoleForm->getValues();

                        $id = $roles->insert(array(
                            'name' => $data['role']
                        ));

                        $parent_role = $roles->find($data['parent_role_id'])->current();

                        if ($parent_role) {
                            $roles_parents = new Acl_Roles_Parents();

                            $roles_parents->insert(array(
                                'role_id' => $id,
                                'parent_role_id' => $parent_role->id
                            ));
                        }

                        $this->resetCache();

                        return $this->_redirect($this->_helper->url->url());
                    }
                    break;

                case 'add-role-parent':
                    if ($addRoleParentForm->isValid($this->getRequest()->getPost())) {
                        $data = $addRoleParentForm->getValues();

                        if ($data['role_id'] != $data['parent_role_id']) {
                            $roles_parents = new Acl_Roles_Parents();

                            $roles_parents->insert(array(
                                'role_id' => $data['role_id'],
                                'parent_role_id' => $data['parent_role_id']
                            ));
                        }

                        $this->resetCache();

                        return $this->_redirect($this->_helper->url->url());
                    }
                    break;

                case 'add-rule':
                    if ($addRuleForm->isValid($this->getRequest()->getPost())) {
                        $data = $addRuleForm->getValues();

                        $allowed = new Acl_Roles_Privileges_Allowed();
                        $denied = new Acl_Roles_Privileges_Denied();

                        $db = $allowed->getAdapter();
                        $where = array(
                            $db->quoteInto('role_id = ?', $data['role_id']),
                            $db->quoteInto('privilege_id = ?', $data['privilege_id'])
                        );

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

                        return $this->_redirect($this->_helper->url->url());
                    }
                    break;
            }
        }

        $rules = array();

        $arpaTable = new Acl_Roles_Privileges_Allowed();
        foreach ($arpaTable->fetchAll() as $row) {
            $privilege = $row->findParentAcl_Resources_Privileges();
            $rules[] = array(
                'allowed'   => true,
                'role'      => $row->findParentAcl_Roles()->name,
                'privilege' => $privilege->name,
                'resource'  => $privilege->findParentAcl_Resources()->name
            );
        }

        $arpdTable = new Acl_Roles_Privileges_Denied();
        foreach ($arpdTable->fetchAll() as $row) {
            $privilege = $row->findParentAcl_Resources_Privileges();
            $rules[] = array(
                'allowed'   => false,
                'role'      => $row->findParentAcl_Roles()->name,
                'privilege' => $privilege->name,
                'resource'  => $privilege->findParentAcl_Resources()->name
            );
        }

        $this->view->assign(array(
            'addRuleForm'       => $addRuleForm,
            'addRoleForm'       => $addRoleForm,
            'addRoleParentForm' => $addRoleParentForm,
            'resources'         => $resources->fetchAll(),
            'privileges'        => new Acl_Resources_Privileges(),
            'roles'             => $roles->fetchAll(),
            'rules'             => $rules
        ));
    }
}