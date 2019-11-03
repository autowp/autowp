<?php

namespace Application\Controller\Frontend\Service;

use Application\ItemNameFormatter;
use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Application\Service\Mosts;
use Application\Service\SpecificationsService;
use Autowp\Comments\CommentsService;
use Autowp\TextStorage\Service;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CatalogueController as Controller;

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
        $config = $container->get('Config');
        $tables = $container->get('TableManager');
        return new Controller(
            $container->get(Service::class),
            $container->get('longCache'),
            $container->get(SpecificationsService::class),
            $container->get(ItemParent::class),
            $container->get(ItemNameFormatter::class),
            $config['mosts_min_vehicles_count'],
            $container->get(CommentsService::class),
            $container->get(Item::class),
            $container->get(Perspective::class),
            $tables->get('links'),
            $container->get(Mosts::class),
            $container->get(VehicleType::class),
            $container->get(Picture::class),
            $container->get(Brand::class),
            $container->get(User::class),
            $container->get('HttpRouter'),
            $container->get('MvcTranslator')
        );
    }
}
