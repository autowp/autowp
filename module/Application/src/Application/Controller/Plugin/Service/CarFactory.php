<?php

namespace Application\Controller\Plugin\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Plugin\Car as Plugin;

class CarFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Plugin(
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\ItemNameFormatter::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\ItemParent::class),
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\Twins::class),
            $container->get('HttpRouter')
        );
    }
}
