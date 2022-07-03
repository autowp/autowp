<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\HostManager;
use Application\Hydrator\Api\ItemParentHydrator;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\UserItemSubscribe;
use Application\Model\VehicleType;
use Application\Service\SpecificationsService;
use Autowp\Message\MessageService;
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
            $filters->get('api_item_parent_post'),
            $filters->get('api_item_parent_put'),
            $container->get(ItemParent::class),
            $container->get(SpecificationsService::class),
            $container->get(HostManager::class),
            $container->get(MessageService::class),
            $container->get(UserItemSubscribe::class),
            $container->get(Item::class),
            $container->get(VehicleType::class)
        );
    }
}
