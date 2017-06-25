<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ItemLinkControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');

        return new ItemLinkController(
            $tables->get('links'),
            $hydrators->get(\Application\Hydrator\Api\ItemLinkHydrator::class),
            $filters->get('api_item_link_index'),
            $filters->get('api_item_link_put'),
            $filters->get('api_item_link_post')
        );
    }
}
