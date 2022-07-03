<?php

declare(strict_types=1);

namespace Application\Command;

use Application\Model\ItemParent;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CatalogueRefreshBrandVehicleCommandFactory implements FactoryInterface
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
    ): CatalogueRefreshBrandVehicleCommand {
        return new CatalogueRefreshBrandVehicleCommand(
            'catalogue-refresh-brand-vehicle',
            $container->get(ItemParent::class)
        );
    }
}
