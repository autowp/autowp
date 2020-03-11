<?php

namespace Autowp\User\Controller\Plugin;

use Autowp\User\Model\User as UserModel;
use Interop\Container\ContainerInterface;
use Laminas\Permissions\Acl\Acl;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): User
    {
        return new User(
            $container->get(Acl::class),
            $container->get(UserModel::class)
        );
    }
}
