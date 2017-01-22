<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\AccountController as Controller;

class AccountControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        return new Controller(
            $container->get(\Application\Service\UsersService::class),
            $container->get('AccountEmailForm'),
            $container->get('AccountProfileForm'),
            $container->get('AccountSettingsForm'),
            $container->get('AccountPhotoForm'),
            $container->get('ChangePasswordForm'),
            $container->get('DeleteUserForm'),
            $container->get(\Autowp\ExternalLoginService\Factory::class),
            $config['hosts'],
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Autowp\Message\MessageService::class)
        );
    }
}
