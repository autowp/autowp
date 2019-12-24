<?php

namespace Application\Controller\Api\Service;

use Application\Model\Item;
use Application\Model\VehicleType;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\Api\ItemVehicleTypeController as Controller;

class ItemVehicleTypeControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Controller
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(VehicleType::class),
            $container->get(Item::class)
        );
    }
}
