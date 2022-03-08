<?php

declare(strict_types=1);

namespace Application\Model;

use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserItemSubscribeFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UserItemSubscribe
    {
        $tables = $container->get('TableManager');
        return new UserItemSubscribe(
            $tables->get('user_item_subscribe'),
            $container->get(User::class)
        );
    }
}
