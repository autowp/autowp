<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CatalogueController as Controller;

class CatalogueControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $tables = $container->get('TableManager');
        return new Controller(
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get('longCache'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\ItemParent::class),
            $container->get(\Application\ItemNameFormatter::class),
            $config['mosts_min_vehicles_count'],
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Perspective::class),
            $tables->get('links'),
            $container->get(\Application\Service\Mosts::class),
            $container->get(\Application\Model\VehicleType::class),
            $container->get(\Application\Model\Picture::class),
            $tables->get('modification'),
            $tables->get('modification_group'),
            $container->get(\Application\Model\Brand::class),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
