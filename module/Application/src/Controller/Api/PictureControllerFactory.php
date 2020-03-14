<?php

namespace Application\Controller\Api;

use Application\DuplicateFinder;
use Application\HostManager;
use Application\Hydrator\Api\PictureHydrator;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Log;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\PictureModerVote;
use Application\Model\UserPicture;
use Application\Service\PictureService;
use Application\Service\TelegramService;
use Autowp\Comments\CommentsService;
use Autowp\Message\MessageService;
use Autowp\TextStorage\Service;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): PictureController
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        return new PictureController(
            $hydrators->get(PictureHydrator::class),
            $container->get(PictureItem::class),
            $container->get(DuplicateFinder::class),
            $container->get(UserPicture::class),
            $container->get(Log::class),
            $container->get(HostManager::class),
            $container->get(TelegramService::class),
            $container->get(MessageService::class),
            $container->get(CarOfDay::class),
            $filters->get('api_picture_item'),
            $filters->get('api_picture_post'),
            $filters->get('api_picture_list'),
            $filters->get('api_picture_list_public'),
            $filters->get('api_picture_edit'),
            $container->get(Service::class),
            $container->get(CommentsService::class),
            $container->get(PictureModerVote::class),
            $container->get(Item::class),
            $container->get(Picture::class),
            $container->get(User::class),
            $container->get(PictureService::class),
            $container->get(Catalogue::class)
        );
    }
}
