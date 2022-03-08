<?php

declare(strict_types=1);

namespace Application\Service;

use Autowp\Image\Storage;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UsersServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UsersService
    {
        $tables = $container->get('TableManager');
        return new UsersService(
            $container->get(Storage::class),
            $container->get(User::class),
            $tables->get('log_events_user')
        );
    }
}
