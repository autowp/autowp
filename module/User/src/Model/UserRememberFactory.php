<?php

namespace Autowp\User\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserRememberFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UserRemember
    {
        $tables = $container->get('TableManager');
        $config = $container->get('Config');
        return new UserRemember(
            $tables->get('user_remember'),
            $config['users']['salt']
        );
    }
}
