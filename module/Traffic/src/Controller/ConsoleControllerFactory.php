<?php

namespace Autowp\Traffic\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConsoleControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ConsoleController(
            $container->get(\Autowp\Traffic\TrafficControl::class)
        );
    }
}
