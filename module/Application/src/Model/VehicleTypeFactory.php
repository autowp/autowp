<?php

declare(strict_types=1);

namespace Application\Model;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VehicleTypeFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): VehicleType
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
