<?php

namespace Application\Model;

use Autowp\TextStorage\Service;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Item
    {
        $tables = $container->get('TableManager');
        return new Item(
            $tables->get('spec'),
            $tables->get('item_point'),
            $tables->get('car_types_parents'),
            $tables->get('item_language'),
            $container->get(Service::class),
            $tables->get('item'),
            $tables->get('item_parent'),
            $tables->get('item_parent_language'),
            $tables->get('item_parent_cache')
        );
    }
}
