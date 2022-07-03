<?php

namespace Application\Model;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemAliasFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): ItemAlias
    {
        $tables = $container->get('TableManager');
        return new ItemAlias(
            $tables->get('brand_alias'),
            $container->get(Item::class)
        );
    }
}
