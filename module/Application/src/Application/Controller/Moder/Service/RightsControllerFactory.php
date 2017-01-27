<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\RightsController as Controller;

class RightsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Zend\Permissions\Acl\Acl::class),
            $container->get('longCache'),
            $container->get('ModerAclRoleForm'),
            $container->get('ModerAclRuleForm'),
            $container->get('ModerAclRoleParentForm'),
            $container->get(\Zend\Db\Adapter\AdapterInterface::class)
        );
    }
}
