<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\PictureNameFormatter;
use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GalleryControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): GalleryController
    {
        return new GalleryController(
            $container->get(Picture::class),
            $container->get(PictureItem::class),
            $container->get(Item::class),
            $container->get(CommentsService::class),
            $container->get(PictureNameFormatter::class),
            $container->get(ItemNameFormatter::class),
            $container->get(Storage::class)
        );
    }
}
