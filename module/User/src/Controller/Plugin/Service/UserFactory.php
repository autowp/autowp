<?php

namespace Autowp\User\Controller\Plugin\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new \Autowp\User\Controller\Plugin\User(
            $container->get(\Zend\Permissions\Acl\Acl::class),
            $container->get('Config')['hosts']
        );
    }
}
