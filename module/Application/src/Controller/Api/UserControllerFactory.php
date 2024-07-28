<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Autowp\Image\Storage;
use Autowp\User\Model\User;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): UserController
    {
        $filters = $container->get('InputFilterManager');
        $config  = $container->get('Config');

        return new UserController(
            $filters->get('api_user_put'),
            $filters->get('api_user_photo_post'),
            $container->get(User::class),
            $config['hosts'],
            $container->get(Storage::class)
        );
    }
}
