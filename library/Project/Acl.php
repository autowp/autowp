<?php

class Project_Acl extends Zend_Acl
{
    public function __construct()
    {
        $this->_load();
    }

    private function _load()
    {
        $roles = new Acl_Roles();
        $loaded = array();
        foreach ($roles->fetchAll() as $role) {
            $this->_addRole($roles, $role, $loaded, 1);
        }

        $resources = new Acl_Resources();
        foreach ($resources->fetchAll() as $resource) {
            $this->add(new Zend_Acl_Resource($resource->name));
        }

        $allowed = new Acl_Roles_Privileges_Allowed();
        foreach ($allowed->fetchAll() as $allow) {
            $privilege = $allow->findParentAcl_Resources_Privileges();

            $this->allow(
                $allow->findParentAcl_Roles()->name,
                $privilege->findParentAcl_Resources()->name,
                $privilege->name
            );
        }

        $denied = new Acl_Roles_Privileges_Denied();
        foreach ($denied->fetchAll() as $deny) {
            $privilege = $deny->findParentAcl_Resources_Privileges();
            $this->deny(
                $deny->findParentAcl_Roles()->name,
                $privilege->findParentAcl_Resources()->name,
                $privilege->name
            );
        }
    }

    private function _addRole($roles, $role, array &$loaded, $deep)
    {
        if ($deep > 10)
            throw new Exception('Roles deep overflow');

        if (in_array($role->name, $loaded))
            return;

        // parent roles
        $select = $roles->select()
            ->from($roles)
            ->join('acl_roles_parents', 'acl_roles.id=acl_roles_parents.parent_role_id', null)
            ->where('acl_roles_parents.role_id=?', $role->id);

        $parents = array();
        foreach ($roles->fetchAll($select) as $parentRole) {
            $parents[] = $parentRole->name;

            $this->_addRole($roles, $parentRole, $loaded, $deep+1);
        }

        $this->addRole(new Zend_Acl_Role($role->name), $parents);

        $loaded[] = $role->name;
    }
}