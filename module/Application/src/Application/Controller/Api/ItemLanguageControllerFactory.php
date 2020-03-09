<?php

namespace Application\Controller\Api;

use Application\HostManager;
use Application\Hydrator\Api\ItemLanguageHydrator;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\UserItemSubscribe;
use Autowp\Message\MessageService;
use Autowp\TextStorage\Service;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemLanguageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ItemLanguageController
    {
        $tables    = $container->get('TableManager');
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');

        return new ItemLanguageController(
            $tables->get('item_language'),
            $container->get(Service::class),
            $hydrators->get(ItemLanguageHydrator::class),
            $container->get(ItemParent::class),
            $container->get(HostManager::class),
            $filters->get('api_item_language_put'),
            $container->get(MessageService::class),
            $container->get(UserItemSubscribe::class),
            $container->get(Item::class)
        );
    }
}
