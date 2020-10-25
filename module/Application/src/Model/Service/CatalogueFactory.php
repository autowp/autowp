<?php

namespace Application\Model\Service;

use Application\Model\Catalogue;
use Application\Model\ItemParent;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CatalogueFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Catalogue
    {
        $tables = $container->get('TableManager');
        return new Catalogue(
            $container->get(ItemParent::class),
            $tables->get('item')
        );
    }
}
