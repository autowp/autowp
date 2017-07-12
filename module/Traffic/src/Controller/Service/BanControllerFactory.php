<?php

namespace Autowp\Traffic\Controller\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Autowp\Traffic\Controller\BanController;

class BanControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new BanController(
            $container->get(\Autowp\Traffic\TrafficControl::class)
        );
    }
}
