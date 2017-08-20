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
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Controller(
            $container->get(\Application\Service\UsersService::class),
            $container->get('LoginForm'),
            $container->get('ExternalLoginServiceManager'),
            $container->get('Config')['hosts'],
            $container->get(\Autowp\User\Model\UserRemember::class),
            $container->get(\Application\Model\UserAccount::class),
            $tables->get('login_state'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
