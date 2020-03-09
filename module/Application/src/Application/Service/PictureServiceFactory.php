<?php

namespace Application\Service;

use Application\DuplicateFinder;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\UserPicture;
use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Autowp\TextStorage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): PictureService
    {
        return new PictureService(
            $container->get(Picture::class),
            $container->get(CommentsService::class),
            $container->get(Storage::class),
            $container->get(TelegramService::class),
            $container->get(DuplicateFinder::class),
            $container->get(PictureItem::class),
            $container->get(UserPicture::class),
            $container->get(TextStorage\Service::class)
        );
    }
}
