<?php

namespace Application\Controller\Plugin\Service;

use Application\Controller\Plugin\Car as Plugin;
use Application\ItemNameFormatter;
use Application\Model\Item;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CarFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Plugin
    {
        return new Plugin(
            $container->get(ItemNameFormatter::class),
            $container->get(Item::class)
        );
    }
}
