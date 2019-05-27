<?php

namespace Application\Controller\Api;

use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class BrandsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return BrandsController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new BrandsController(
            $container->get('longCache'),
            $container->get(Brand::class),
            $container->get(VehicleType::class),
            $container->get(Item::class),
            $container->get(Picture::class),
            $container->get('MvcTranslator'),
            $container->get('HttpRouter')
        );
    }
}
