<?php

namespace Application\Provider\UserId;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class OAuth2UserIdProviderFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): OAuth2UserIdProvider {
        $config = $container->get('Config');

        return new OAuth2UserIdProvider($config);
    }

    public function createService(ServiceLocatorInterface $services): OAuth2UserIdProvider
    {
        return $this($services, self::class);
    }
}
