<?php

namespace Application\Model\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Model\BrandNav as Model;

class BrandNavFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Model(
            $container->get('fastCache'),
            $container->get('MvcTranslator'),
            $container->get('HttpRouter'),
            $container->get(\Application\Model\ItemParent::class),
            $container->get(\Application\Model\ItemAlias::class),
            $container->get(\Application\Model\DbTable\Picture::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\VehicleType::class),
            $container->get(\Application\Model\Brand::class)
        );
    }
}
