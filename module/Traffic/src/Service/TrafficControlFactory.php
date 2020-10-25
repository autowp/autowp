<?php

namespace Autowp\Traffic\Service;

use Autowp\Traffic\TrafficControl;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TrafficControlFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): TrafficControl
    {
        $config = $container->get('Config');
        return new TrafficControl(
            $config['traffic']['url'],
            $container->get('RabbitMQ')
        );
    }
}
