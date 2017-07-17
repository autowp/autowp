<?php

namespace Application\Controller\Console\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Console\CatalogueController as Controller;

class CatalogueControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\Model\ItemParent::class),
            $container->get(\Application\Model\PictureItem::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\HostManager::class),
            $container->get(\Application\Service\TelegramService::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get(\Application\DuplicateFinder::class)
        );
    }
}
