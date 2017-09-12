<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class RestorePasswordControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        $config = $container->get('Config');

        return new RestorePasswordController(
            $container->get(\Application\Service\UsersService::class),
            $filters->get('api_restore_password_request'),
            $filters->get('api_restore_password_new'),
            $container->get(\Zend\Mail\Transport\TransportInterface::class),
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\User\Model\UserPasswordRemind::class),
            $container->get(\Autowp\User\Model\User::class),
            $config['recaptcha'],
            (bool)getenv('AUTOWP_CAPTCHA')
        );
    }
}
