<?php

namespace Application\Model\Service;

use Application\ItemNameFormatter;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\Twins;
use Application\PictureNameFormatter;
use Application\Service\SpecificationsService;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CarOfDayFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return CarOfDay
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new CarOfDay(
            $tables->get('of_day'),
            $container->get(ItemNameFormatter::class),
            $container->get(Storage::class),
            $container->get(Catalogue::class),
            $container->get('HttpRouter'),
            $container->get('MvcTranslator'),
            $container->get(SpecificationsService::class),
            $container->get(Item::class),
            $container->get(Perspective::class),
            $container->get(ItemParent::class),
            $container->get(Picture::class),
            $container->get(Twins::class),
            $container->get(PictureNameFormatter::class)
        );
    }
}
