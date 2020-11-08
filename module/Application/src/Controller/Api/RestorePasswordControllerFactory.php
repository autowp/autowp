<?php

namespace Application\Controller\Api;

use Application\HostManager;
use Application\Service\UsersService;
use Autowp\User\Model\User;
use Autowp\User\Model\UserPasswordRemind;
use Interop\Container\ContainerInterface;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function getenv;

class RestorePasswordControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): RestorePasswordController {
        $filters = $container->get('InputFilterManager');
        $config  = $container->get('Config');

        return new RestorePasswordController(
            $container->get(UsersService::class),
            $filters->get('api_restore_password_request'),
            $filters->get('api_restore_password_new'),
            $container->get(TransportInterface::class),
            $container->get(HostManager::class),
            $container->get(UserPasswordRemind::class),
            $container->get(User::class),
            $config['recaptcha'],
            (bool) $config['captcha']
        );
    }
}
