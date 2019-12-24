<?php

namespace Application\Model\Service;

use Application\Model\Item;
use Application\Model\ItemAlias;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Model\BrandNav as Model;

class BrandNavFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Model
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Model(
            $container->get('fastCache'),
            $container->get('MvcTranslator'),
            $container->get('HttpRouter'),
            $container->get(ItemParent::class),
            $container->get(ItemAlias::class),
            $container->get(Picture::class),
            $container->get(Item::class),
            $container->get(VehicleType::class)
        );
    }
}
