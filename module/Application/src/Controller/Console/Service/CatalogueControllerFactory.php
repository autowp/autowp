<?php

namespace Application\Controller\Console\Service;

use Application\Controller\Console\CatalogueController;
use Application\HostManager;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Service\TelegramService;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CatalogueControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): CatalogueController
    {
        return new CatalogueController(
            $container->get(ItemParent::class),
            $container->get(HostManager::class),
            $container->get(TelegramService::class),
            $container->get(MessageService::class),
            $container->get(Item::class),
            $container->get(Picture::class),
            $container->get(User::class)
        );
    }
}
