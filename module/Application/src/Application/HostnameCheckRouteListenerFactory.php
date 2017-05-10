<?php

namespace Application;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class HostnameCheckRouteListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        return new HostnameCheckRouteListener(
            $config['hostname_whitelist']
        );
    }
}