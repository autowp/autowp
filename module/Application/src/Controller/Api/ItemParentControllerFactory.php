<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemParentHydrator;
use Application\Model\ItemParent;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemParentControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): ItemParentController {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        return new ItemParentController(
            $hydrators->get(ItemParentHydrator::class),
            $filters->get('api_item_parent_list'),
            $filters->get('api_item_parent_item'),
            $container->get(ItemParent::class)
        );
    }
}
