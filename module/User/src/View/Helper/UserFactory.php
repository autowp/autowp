<?php

namespace Autowp\User\View\Helper;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new User(
            $container->get(\Zend\Permissions\Acl\Acl::class),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
