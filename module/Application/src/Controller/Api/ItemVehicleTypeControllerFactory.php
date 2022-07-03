<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Controller\Api\ItemVehicleTypeController as Controller;
use Application\Model\Item;
use Application\Model\VehicleType;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemVehicleTypeControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Controller
    {
        return new Controller(
            $container->get(VehicleType::class),
            $container->get(Item::class)
        );
    }
}
