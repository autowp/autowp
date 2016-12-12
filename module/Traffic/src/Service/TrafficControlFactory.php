<?php

namespace Autowp\Traffic\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TrafficControlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new \Autowp\Traffic\TrafficControl(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class)
        );
    }
}
