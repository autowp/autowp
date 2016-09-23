<?php

namespace Application\Permissions;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Role\GenericRole;
use Zend\Permissions\Acl\Resource\GenericResource;
use Interop\Container\ContainerInterface;

use Exception;

use Acl_Resources;
use Acl_Roles;
use Acl_Roles_Privileges_Allowed;
use Acl_Roles_Privileges_Denied;

class AclFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $services = $container->get('ServiceManager');

        $cache = $services->get('longCache');

        $key = 'acl_cache_key';

        $acl = $cache->getItem($key, $success);

        if (!$success) {

            $acl = new Acl();

            $this->load($acl);

            $cache->setItem($key, $acl);
        }

        if (!$acl) {
            throw new Exception('NULL');
        }

        return $acl;
    }

    /**
     * @param ServiceLocatorInterface $controllers
     * @return OAuth2Plugin
     */
    public function createService(ServiceLocatorInterface $controllers)
    {
        return $this($controllers, AclFactory::class);
    }

    private function load(Acl $acl)
    {
        $roles = new Acl_Roles();
        $loaded = array();
        foreach ($roles->fetchAll() as $role) {
            $this->addRole($acl, $roles, $role, $loaded, 1);
        }

        $resources = new Acl_Resources();
        foreach ($resources->fetchAll() as $resource) {
            $acl->addResource(new GenericResource($resource->name));
        }

        $allowed = new Acl_Roles_Privileges_Allowed();
        foreach ($allowed->fetchAll() as $allow) {
            $privilege = $allow->findParentAcl_Resources_Privileges();

            $acl->allow(
                $allow->findParentAcl_Roles()->name,
                $privilege->findParentAcl_Resources()->name,
                $privilege->name
            );
        }

        $denied = new Acl_Roles_Privileges_Denied();
        foreach ($denied->fetchAll() as $deny) {
            $privilege = $deny->findParentAcl_Resources_Privileges();
            $acl->deny(
                $deny->findParentAcl_Roles()->name,
                $privilege->findParentAcl_Resources()->name,
                $privilege->name
            );
        }
    }

    private function addRole(Acl $acl, $roles, $role, array &$loaded, $deep)
    {
        if ($deep > 10) {
            throw new Exception('Roles deep overflow');
        }

        if (in_array($role->name, $loaded)) {
            return;
        }

        // parent roles
        $select = $roles->select()
            ->from($roles)
            ->join('acl_roles_parents', 'acl_roles.id=acl_roles_parents.parent_role_id', null)
            ->where('acl_roles_parents.role_id = ?', $role->id);

        $parents = array();
        foreach ($roles->fetchAll($select) as $parentRole) {
            $parents[] = $parentRole->name;

            $this->addRole($acl, $roles, $parentRole, $loaded, $deep+1);
        }

        $acl->addRole(new GenericRole($role->name), $parents);

        $loaded[] = $role->name;
    }
}
