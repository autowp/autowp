<?php

namespace Autowp\User\Model;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserPasswordRemindFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        $config = $container->get('Config');
        return new UserPasswordRemind(
            $tables->get('user_password_remind'),
            $config['users']['salt']
        );
    }
}
