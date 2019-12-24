<?php

namespace Application\Controller\Plugin\Service;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\Twins;
use Application\Service\SpecificationsService;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\Plugin\Car as Plugin;

class CarFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Plugin
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Plugin(
            $container->get(SpecificationsService::class),
            $container->get(ItemNameFormatter::class),
            $container->get(Item::class),
            $container->get(ItemParent::class),
            $container->get(Picture::class),
            $container->get(Twins::class),
            $container->get('HttpRouter')
        );
    }
}
