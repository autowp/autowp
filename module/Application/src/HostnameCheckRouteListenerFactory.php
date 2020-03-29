<?php

namespace Application;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class HostnameCheckRouteListenerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): HostnameCheckRouteListener {
        $config = $container->get('Config');
        return new HostnameCheckRouteListener(
            $config['hostname_whitelist'],
            $config['force_https']
        );
    }
}
