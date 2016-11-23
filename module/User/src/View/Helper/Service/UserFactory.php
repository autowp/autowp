<?php

namespace Autowp\User\View\Helper\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new \Autowp\User\View\Helper\User(
            $container->get(\Zend\Permissions\Acl\Acl::class)
        );
    }
}
