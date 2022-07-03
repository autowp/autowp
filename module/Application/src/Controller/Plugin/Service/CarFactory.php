<?php

declare(strict_types=1);

namespace Application\Controller\Plugin\Service;

use Application\Controller\Plugin\Car as Plugin;
use Application\ItemNameFormatter;
use Application\Model\Item;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CarFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Plugin
    {
        return new Plugin(
            $container->get(ItemNameFormatter::class),
            $container->get(Item::class)
        );
    }
}
