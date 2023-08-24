<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\HostManager;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\UserItemSubscribe;
use Autowp\Message\MessageService;
use Autowp\TextStorage\Service;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemLanguageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): ItemLanguageController {
        $tables  = $container->get('TableManager');
        $filters = $container->get('InputFilterManager');

        return new ItemLanguageController(
            $tables->get('item_language'),
            $container->get(Service::class),
            $container->get(ItemParent::class),
            $container->get(HostManager::class),
            $filters->get('api_item_language_put'),
            $container->get(MessageService::class),
            $container->get(UserItemSubscribe::class),
            $container->get(Item::class)
        );
    }
}
