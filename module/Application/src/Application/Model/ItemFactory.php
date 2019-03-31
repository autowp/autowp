<?php

namespace Application\Model;

use Autowp\TextStorage\Service;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ItemFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Item
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
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
