<?php

declare(strict_types=1);

namespace Autowp\User\Service;

use Application\Model\UserAccount;
use Autowp\ZFComponents\Db\TableManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class OAuthFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): OAuth
    {
        $config = $container->get('Config');
        $tables = $container->get(TableManager::class);
        return new OAuth(
            $container->get(UserAccount::class),
            $container->get('Request'),
            $config['keycloak'],
            $container->get('longCache'),
            $config['hosts'],
            $tables->get('users')
        );
    }
}
