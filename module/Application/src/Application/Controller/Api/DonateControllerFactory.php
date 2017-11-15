<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class DonateControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        return new DonateController(
            $container->get(\Application\Model\CarOfDay::class),
            $config['yandex'],
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\ItemParent::class),
            $container->get(\Application\Model\Brand::class)
        );
    }
}
