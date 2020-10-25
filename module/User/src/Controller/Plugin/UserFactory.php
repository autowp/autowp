<?php

namespace Autowp\User\Controller\Plugin;

use Autowp\User\Model\User as UserModel;
use Autowp\User\Service\OAuth;
use Interop\Container\ContainerInterface;
use Laminas\Permissions\Acl\Acl;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): User
    {
        return new User(
            $container->get(Acl::class),
            $container->get(UserModel::class),
            $container->get(OAuth::class)
        );
    }
}
