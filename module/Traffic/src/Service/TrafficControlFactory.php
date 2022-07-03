<?php

declare(strict_types=1);

namespace Autowp\Traffic\Service;

use Autowp\Traffic\TrafficControl;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TrafficControlFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): TrafficControl
    {
        $config = $container->get('Config');
        return new TrafficControl(
            $config['traffic']['url'],
            $container->get('RabbitMQ')
        );
    }
}
