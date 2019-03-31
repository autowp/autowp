<?php

namespace Application\Service;

use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MostsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Mosts
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Mosts(
            $container->get(SpecificationsService::class),
            $container->get(Perspective::class),
            $container->get(VehicleType::class),
            $container->get(Picture::class),
            $tables->get('attrs_attributes'),
            $tables->get('item')
        );
    }
}
