<?php

namespace Autowp\Traffic\Service;

use Autowp\Traffic\TrafficControl;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TrafficControlFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return TrafficControl
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        return new TrafficControl(
            $config['traffic']['url'],
            $container->get('RabbitMQ')
        );
    }
}
