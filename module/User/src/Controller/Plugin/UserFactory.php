<?php

namespace Autowp\User\Controller\Plugin;

use Interop\Container\ContainerInterface;
use Zend\Permissions\Acl\Acl;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return User
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new User(
            $container->get(Acl::class),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
