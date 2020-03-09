<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VehicleTypeFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): VehicleType
    {
        $tables = $container->get('TableManager');
        return new VehicleType(
            $tables->get('vehicle_vehicle_type'),
            $tables->get('item_parent'),
            $tables->get('car_types_parents'),
            $tables->get('car_types')
        );
    }
}
