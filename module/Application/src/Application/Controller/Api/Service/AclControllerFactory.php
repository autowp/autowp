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
        return new Controller(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $container->get('longCache'),
            $filters->get('api_acl_roles_list'),
            $filters->get('api_acl_roles_post'),
            $filters->get('api_acl_roles_role_parents_post'),
            $filters->get('api_acl_rules_post')
        );
    }
}
