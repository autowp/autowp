<?php

namespace Autowp\User\Controller\Plugin;

use Autowp\User\Model\User as UserModel;
use Autowp\User\Service\OAuth;
use Casbin\Enforcer;
use Interop\Container\ContainerInterface;
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
            $container->get(Enforcer::class),
            $container->get(UserModel::class),
            $container->get(OAuth::class)
        );
    }
}
