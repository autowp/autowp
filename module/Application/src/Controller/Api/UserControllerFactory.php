<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\UserHydrator;
use Application\Service\UsersService;
use Autowp\Image\Storage;
use Autowp\User\Model\User;
use Autowp\User\Model\UserRename;
use Interop\Container\ContainerInterface;
use Laminas\Permissions\Acl\Acl;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function getenv;

class UserControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UserController
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        $config    = $container->get('Config');

        return new UserController(
            $container->get(Acl::class),
            $hydrators->get(UserHydrator::class),
            $filters->get('api_user_item'),
            $filters->get('api_user_list'),
            $filters->get('api_user_post'),
            $filters->get('api_user_put'),
            $filters->get('api_user_photo_post'),
            $container->get(UsersService::class),
            $container->get(User::class),
            $config['recaptcha'],
            (bool) getenv('AUTOWP_CAPTCHA'),
            $container->get(UserRename::class),
            $config['hosts'],
            $container->get(Storage::class)
        );
    }
}
