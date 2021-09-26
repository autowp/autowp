<?php

declare(strict_types=1);

namespace Autowp\User\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserPasswordRemindFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UserPasswordRemind
    {
        $tables = $container->get('TableManager');
        $config = $container->get('Config');
        return new UserPasswordRemind(
            $tables->get('user_password_remind'),
            $config['users']['salt']
        );
    }
}
