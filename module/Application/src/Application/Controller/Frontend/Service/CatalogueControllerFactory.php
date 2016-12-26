<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CatalogueController as Controller;

class CatalogueControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get('longCache'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\BrandVehicle::class),
            $container->get(\Application\VehicleNameFormatter::class)
        );
    }
}
