<?php

namespace Autowp\Traffic\Controller;

use Autowp\Traffic\TrafficControl;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BanControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): BanController
    {
        return new BanController(
            $container->get(TrafficControl::class)
        );
    }
}
