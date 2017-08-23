<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\AclController as Controller;

class AclControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        $tables = $container->get('TableManager');
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
