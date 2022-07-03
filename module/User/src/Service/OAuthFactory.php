<?php

declare(strict_types=1);

namespace Autowp\User\Service;

use Autowp\ZFComponents\Db\TableManager;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class OAuthFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): OAuth
    {
        $config = $container->get('Config');
        $tables = $container->get(TableManager::class);
        return new OAuth(
            $container->get('Request'),
            $config['keycloak'],
            $container->get('longCache'),
            $config['hosts'],
            $tables->get('users')
        );
    }
}
