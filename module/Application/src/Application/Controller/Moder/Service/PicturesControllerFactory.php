<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\PicturesController as Controller;

class PicturesControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\HostManager::class),
            $container->get(\Application\Model\DbTable\Picture::class),
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get('ModerPictureForm'),
            $container->get('ModerPictureCopyrightsForm'),
            $container->get('ModerPictureVoteForm'),
            $container->get('BanForm'),
            $container->get(\Application\PictureNameFormatter::class),
            $container->get(\Application\Service\TelegramService::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Autowp\Traffic\TrafficControl::class),
            $container->get(\Application\Model\PictureItem::class),
            $container->get(\Application\DuplicateFinder::class),
            $container->get(\Autowp\Comments\CommentsService::class)
        );
    }
}
