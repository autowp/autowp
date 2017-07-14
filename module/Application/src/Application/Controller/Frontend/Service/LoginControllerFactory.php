<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\LoginController as Controller;

class LoginControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\Service\UsersService::class),
            $container->get('LoginForm'),
            $container->get(\Autowp\ExternalLoginService\Factory::class),
            $container->get('Config')['hosts'],
            $container->get(\Autowp\User\Model\UserRemember::class)
        );
    }
}
