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
        $tables = $container->get('TableManager');
        return new Mosts(
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Model\VehicleType::class),
            $container->get(\Application\Model\Picture::class),
            $tables->get('attrs_attributes'),
            $tables->get('item')
        );
    }
}
