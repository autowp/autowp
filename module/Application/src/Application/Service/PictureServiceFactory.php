<?php

namespace Application\Service;

use Application\DuplicateFinder;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\UserPicture;
use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PictureServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return PictureService
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new PictureService(
            $container->get(Picture::class),
            $container->get(CommentsService::class),
            $container->get(Storage::class),
            $container->get(TelegramService::class),
            $container->get(DuplicateFinder::class),
            $container->get(PictureItem::class),
            $container->get(UserPicture::class)
        );
    }
}
