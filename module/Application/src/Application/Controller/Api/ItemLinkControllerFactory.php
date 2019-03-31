<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemLinkHydrator;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ItemLinkControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return ItemLinkController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');

        return new ItemLinkController(
            $tables->get('links'),
            $hydrators->get(ItemLinkHydrator::class),
            $filters->get('api_item_link_index'),
            $filters->get('api_item_link_put'),
            $filters->get('api_item_link_post')
        );
    }
}
