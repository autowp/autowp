<?php

namespace Application\Controller\Api;

use Application\Model\VehicleType;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class VehicleTypesControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return VehicleTypesController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new VehicleTypesController(
            $container->get(VehicleType::class)
        );
    }
}
