<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ItemFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Item(
            $tables->get('spec'),
            $tables->get('item_point'),
            $tables->get('car_types_parents'),
            $tables->get('item_language'),
            $container->get(\Autowp\TextStorage\Service::class),
            $tables->get('item'),
            $tables->get('item_parent'),
            $tables->get('item_parent_cache')
        );
    }
}
