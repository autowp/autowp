<?php

namespace Application\Controller\Api;

use Application\Model\VehicleType;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VehicleTypesControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): VehicleTypesController {
        return new VehicleTypesController(
            $container->get(VehicleType::class)
        );
    }
}
