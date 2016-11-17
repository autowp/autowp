<?php

namespace Application\Controller\Plugin\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Plugin\Car as Plugin;

class CarFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Plugin(
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get(\Application\Service\SpecificationsService::class)
        );
    }
}
