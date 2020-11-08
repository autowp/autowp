<?php

namespace Application\Controller\Api;

use Application\Controller\Api\ItemController as Controller;
use Application\HostManager;
use Application\Hydrator\Api\ItemHydrator;
use Application\Hydrator\Api\Strategy\Image;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\UserItemSubscribe;
use Application\Model\VehicleType;
use Application\Service\SpecificationsService;
use Autowp\Image\Storage;
use Autowp\Message\MessageService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Controller
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        $tables    = $container->get('TableManager');
        return new Controller(
            $hydrators->get(ItemHydrator::class),
            new Image($container),
            $filters->get('api_item_list'),
            $filters->get('api_item_item'),
            $filters->get('api_item_logo_put'),
            $container->get(SpecificationsService::class),
            $container->get(ItemParent::class),
            $container->get(HostManager::class),
            $container->get(MessageService::class),
            $container->get(UserItemSubscribe::class),
            $tables->get('spec'),
            $container->get(Item::class),
            $container->get(VehicleType::class),
            $container->get('InputFilterManager'),
            $container->get(SpecificationsService::class),
            $container->get(Storage::class)
        );
    }
}
