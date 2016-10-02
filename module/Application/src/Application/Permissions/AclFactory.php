<?php

namespace Application\Permissions;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Role\GenericRole;
use Zend\Permissions\Acl\Resource\GenericResource;
use Interop\Container\ContainerInterface;

use Application\Model\DbTable\Acl\Resource;
use Application\Model\DbTable\Acl\ResourcePrivilege;
use Application\Model\DbTable\Acl\Role;
use Application\Model\DbTable\Acl\RolePrivilegeAllowed;
use Application\Model\DbTable\Acl\RolePrivilegeDenied;

use Exception;

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
        $roles = new Role();
        $loaded = [];
        foreach ($roles->fetchAll() as $role) {
            $this->addRole($acl, $roles, $role, $loaded, 1);
        }

        $resources = new Resource();
        foreach ($resources->fetchAll() as $resource) {
            $acl->addResource(new GenericResource($resource->name));
        }

        $allowed = new RolePrivilegeAllowed();
        foreach ($allowed->fetchAll() as $allow) {
            $privilege = $allow->findParentRow(ResourcePrivilege::class);

            $acl->allow(
                $allow->findParentRow(Role::class)->name,
                $privilege->findParentRow(Resource::class)->name,
                $privilege->name
            );
        }

        $denied = new RolePrivilegeDenied();
        foreach ($denied->fetchAll() as $deny) {
            $privilege = $deny->findParentRow(ResourcePrivilege::class);
            $acl->deny(
                $deny->findParentRow(Role::class)->name,
                $privilege->findParentRow(Resource::class)->name,
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

        $parents = [];
        foreach ($roles->fetchAll($select) as $parentRole) {
            $parents[] = $parentRole->name;

            $this->addRole($acl, $roles, $parentRole, $loaded, $deep+1);
        }

        $acl->addRole(new GenericRole($role->name), $parents);

        $loaded[] = $role->name;
    }
}
