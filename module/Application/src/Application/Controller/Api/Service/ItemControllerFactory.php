<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\ItemController as Controller;
use Application\Hydrator\Api\Strategy\Image;

class ItemControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Controller(
            $hydrators->get(\Application\Hydrator\Api\ItemHydrator::class),
            new Image($container),
            $container->get(\Application\ItemNameFormatter::class),
            $filters->get('api_item_list'),
            $filters->get('api_item_item'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\BrandVehicle::class),
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Application\Model\UserItemSubscribe::class),
            $tables->get('spec')
        );
    }
}
