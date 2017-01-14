<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CatalogueController as Controller;

class CatalogueControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        return new Controller(
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get('longCache'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\BrandVehicle::class),
            $container->get(\Application\ItemNameFormatter::class),
            $config['mosts_min_vehicles_count']
        );
    }
}
