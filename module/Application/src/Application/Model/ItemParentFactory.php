<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ItemParentFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $tables = $container->get(\Application\Db\TableManager::class);
        return new ItemParent(
            $config['content_languages'],
            $tables->get('spec'),
            $tables->get('item_parent'),
            $container->get(\Zend_Db_Adapter_Abstract::class),
            $container->get(ItemAlias::class),
            $container->get(Item::class)
        );
    }
}
