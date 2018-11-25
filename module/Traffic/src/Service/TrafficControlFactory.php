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
        $config = $container->get('Config');
        return new \Autowp\Traffic\TrafficControl(
            $config['traffic']['url'],
            $container->get('RabbitMQ')
        );
    }
}
