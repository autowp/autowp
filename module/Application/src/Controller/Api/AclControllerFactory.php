<?php

namespace Application\Controller\Api;

use Application\Controller\Api\AclController as Controller;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AclControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Controller
    {
        $filters = $container->get('InputFilterManager');
        $tables  = $container->get('TableManager');
        return new Controller(
            $container->get('longCache'),
            $filters->get('api_acl_roles_list'),
            $filters->get('api_acl_roles_post'),
            $filters->get('api_acl_roles_role_parents_post'),
            $filters->get('api_acl_rules_post'),
            $tables->get('acl_roles'),
            $tables->get('acl_roles_parents'),
            $tables->get('acl_resources'),
            $tables->get('acl_resources_privileges'),
            $tables->get('acl_roles_privileges_allowed'),
            $tables->get('acl_roles_privileges_denied')
        );
    }
}
