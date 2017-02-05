<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\RestorePasswordController as Controller;

class RestorePasswordControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\Service\UsersService::class),
            $container->get('RestorePasswordForm'),
            $container->get('NewPasswordForm'),
            $container->get(\Zend\Mail\Transport\TransportInterface::class),
            $container->get(\Application\HostManager::class)
        );
    }
}
