<?php

namespace Autowp\Traffic\Controller\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Autowp\Traffic\Controller\ConsoleController;

class ConsoleControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ConsoleController(
            $container->get(\Autowp\Traffic\TrafficControl::class)
        );
    }
}
