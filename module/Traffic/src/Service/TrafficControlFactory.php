<?php

namespace Autowp\Traffic\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TrafficControlFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        $config = $container->get('Config');
        return new \Autowp\Traffic\TrafficControl(
            $config['traffic']['url'],
            $container->get('RabbitMQ'),
            $tables->get('ip_whitelist'),
            $tables->get('ip_monitoring4')
        );
    }
}
