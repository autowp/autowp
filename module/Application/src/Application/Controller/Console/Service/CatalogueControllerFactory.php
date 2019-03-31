<?php

namespace Application\Controller\Console\Service;

use Application\DuplicateFinder;
use Application\HostManager;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Service\SpecificationsService;
use Application\Service\TelegramService;
use Autowp\Message\MessageService;
use Autowp\TextStorage\Service;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Console\CatalogueController as Controller;

class CatalogueControllerFactory implements FactoryInterface
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
        return new Controller(
            $container->get(ItemParent::class),
            $container->get(PictureItem::class),
            $container->get(SpecificationsService::class),
            $container->get(HostManager::class),
            $container->get(TelegramService::class),
            $container->get(MessageService::class),
            $container->get(Service::class),
            $container->get(DuplicateFinder::class),
            $container->get(Item::class),
            $container->get(Picture::class)
        );
    }
}
