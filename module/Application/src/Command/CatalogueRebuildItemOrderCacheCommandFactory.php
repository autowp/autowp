<?php

declare(strict_types=1);

namespace Application\Command;

use Application\Model\Item;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CatalogueRebuildItemOrderCacheCommandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): CatalogueRebuildItemOrderCacheCommand {
        return new CatalogueRebuildItemOrderCacheCommand(
            'catalogue-rebuild-item-order-cache',
            $container->get(Item::class),
        );
    }
}
