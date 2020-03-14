<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemAliasFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ItemAlias
    {
        $tables = $container->get('TableManager');
        return new ItemAlias(
            $tables->get('brand_alias'),
            $container->get(Item::class)
        );
    }
}
