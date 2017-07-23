<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MostsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Mosts(
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Model\VehicleType::class),
            $container->get(\Application\Model\DbTable\Picture::class)
        );
    }
}
