<?php

namespace Application\Permissions;

use Exception;

use Zend\Cache\Storage\StorageInterface;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Role\GenericRole;
use Zend\Permissions\Acl\Resource\GenericResource;
use Interop\Container\ContainerInterface;

class AclFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $services = $container->get('ServiceManager');

        /**
         * @var StorageInterface
         */
        $cache = $services->get('longCache');

        $key = 'acl_cache_key';

        $acl = $cache->getItem($key, $success);

        if (! $success) {
            $acl = new Acl();

            $this->load(
                $acl,
                $container
            );

            $cache->setItem($key, $acl);
        }

        if (! $acl) {
            throw new Exception('NULL');
        }

        return $acl;
    }

    /**
     * @param ServiceLocatorInterface $controllers
     */
    public function createService(ServiceLocatorInterface $controllers)
    {
        return $this($controllers, AclFactory::class);
    }

    private function load(Acl $acl, ContainerInterface $container)
    {
        $tables = $container->get('TableManager');

        $roleTable = $tables->get('acl_roles');
        $resourceTable = $tables->get('acl_resources');
        $privilegeAllowedTable = $tables->get('acl_roles_privileges_allowed');
        $privilegeDeniedTable = $tables->get('acl_roles_privileges_denied');

        $loaded = [];
        foreach ($roleTable->select([]) as $role) {
            $this->addRole($acl, $roleTable, $role, $loaded, 1);
        }

        foreach ($resourceTable->select([]) as $resource) {
            $acl->addResource(new GenericResource($resource['name']));
        }

        $rows = $privilegeAllowedTable->select(function (Sql\Select $select) {
            $select
                ->columns([])
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
        foreach ($rows as $allow) {
            $acl->allow(
                $allow['role'],
                $allow['resource'],
                $allow['privilege']
            );
        }

        $rows = $privilegeDeniedTable->select(function (Sql\Select $select) {
            $select
                ->columns([])
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
        foreach ($rows as $deny) {
            $acl->deny(
                $deny['role'],
                $deny['resource'],
                $deny['privilege']
            );
        }
    }

    private function addRole(Acl $acl, TableGateway $roleTable, $role, array &$loaded, $deep)
    {
        if ($deep > 10) {
            throw new Exception('Roles deep overflow');
        }

        if (in_array($role['name'], $loaded)) {
            return;
        }

        // parent roles
        $rows = $roleTable->select(function (Sql\Select $select) use ($role) {
            $select
                ->join('acl_roles_parents', 'acl_roles.id = acl_roles_parents.parent_role_id', [])
                ->where([
                    'acl_roles_parents.role_id = ?' => $role['id']
                ]);
        });

        $parents = [];
        foreach ($rows as $parentRole) {
            $parents[] = $parentRole['name'];

            $this->addRole($acl, $roleTable, $parentRole, $loaded, $deep + 1);
        }

        $acl->addRole(new GenericRole($role['name']), $parents);

        $loaded[] = $role['name'];
    }
}
