<?php

namespace Application\Controller\Api\Service;

use Application\HostManager;
use Application\Hydrator\Api\ItemHydrator;
use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\UserItemSubscribe;
use Application\Model\VehicleType;
use Application\Service\SpecificationsService;
use Autowp\Message\MessageService;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\ItemController as Controller;
use Application\Hydrator\Api\Strategy\Image;

class ItemControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Controller
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        $tables = $container->get('TableManager');
        return new Controller(
            $hydrators->get(ItemHydrator::class),
            new Image($container),
            $container->get(ItemNameFormatter::class),
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
            $container->get(SpecificationsService::class)
        );
    }
}
