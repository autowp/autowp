<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Model\Picture;
use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\Mail\Transport\TransportInterface;
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
        $config = $container->get('Config');
        $tables = $container->get('TableManager');
        return new UsersService(
            $config['users'],
            $config['hosts'],
            $container->get('MvcTranslator'),
            $container->get(TransportInterface::class),
            $container->get(SpecificationsService::class),
            $container->get(Storage::class),
            $container->get(CommentsService::class),
            $container->get(Picture::class),
            $container->get(User::class),
            $tables->get('log_events_user')
        );
    }
}
