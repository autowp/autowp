<?php

namespace Application\Model\Service;

use Application\ItemNameFormatter;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\PictureNameFormatter;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CarOfDayFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): CarOfDay
    {
        $tables = $container->get('TableManager');
        return new CarOfDay(
            $tables->get('of_day'),
            $container->get(ItemNameFormatter::class),
            $container->get(Storage::class),
            $container->get(Catalogue::class),
            $container->get('MvcTranslator'),
            $container->get(Item::class),
            $container->get(Perspective::class),
            $container->get(Picture::class),
            $container->get(PictureNameFormatter::class)
        );
    }
}
