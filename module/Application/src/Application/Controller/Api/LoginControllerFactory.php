<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class LoginControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        $tables = $container->get('TableManager');
        return new LoginController(
            $container->get(\Application\Service\UsersService::class),
            $filters->get('api_login'),
            $container->get('Config')['hosts'],
            $container->get(\Autowp\User\Model\UserRemember::class),
            $container->get(\Application\Model\UserAccount::class),
            $tables->get('login_state'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
