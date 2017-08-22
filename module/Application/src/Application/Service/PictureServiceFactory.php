<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PictureServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new PictureService(
            $container->get(\Application\Model\Picture::class),
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get(\Autowp\Image\Storage::class),
            $container->get(\Application\Service\TelegramService::class),
            $container->get(\Application\DuplicateFinder::class),
            $container->get(\Application\Model\PictureItem::class),
            $container->get(\Application\Model\UserPicture::class)
        );
    }
}
