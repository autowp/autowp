<?php

namespace Autowp\Traffic\Controller;

use Autowp\Traffic\TrafficControl;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class BanControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return BanController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new BanController(
            $container->get(TrafficControl::class)
        );
    }
}
