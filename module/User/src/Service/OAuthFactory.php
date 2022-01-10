<?php

declare(strict_types=1);

namespace Autowp\User\Service;

use Application\Model\UserAccount;
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
        return new OAuth(
            $container->get(UserAccount::class),
            $container->get('Request'),
            $config['keycloak'],
            $container->get('longCache'),
        );
    }
}
