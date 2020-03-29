<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemParentFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ItemParent
    {
        $config = $container->get('Config');
        $tables = $container->get('TableManager');
        return new ItemParent(
            $config['content_languages'],
            $tables->get('spec'),
            $tables->get('item_parent'),
            $tables->get('item'),
            $tables->get('item_parent_language'),
            $tables->get('item_parent_cache'),
            $container->get(ItemAlias::class),
            $container->get(Item::class)
        );
    }
}
