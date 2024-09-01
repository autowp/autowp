<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\HostManager;
use Application\Hydrator\Api\PictureHydrator;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\UserPicture;
use Application\Service\PictureService;
use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): PictureController
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        return new PictureController(
            $hydrators->get(PictureHydrator::class),
            $container->get(PictureItem::class),
            $container->get(UserPicture::class),
            $container->get(HostManager::class),
            $container->get(MessageService::class),
            $container->get(CarOfDay::class),
            $filters->get('api_picture_item'),
            $filters->get('api_picture_post'),
            $filters->get('api_picture_list'),
            $filters->get('api_picture_list_public'),
            $container->get(CommentsService::class),
            $container->get(Item::class),
            $container->get(Picture::class),
            $container->get(User::class),
            $container->get(PictureService::class),
            $container->get(Catalogue::class),
            $container->get(Storage::class)
        );
    }
}
