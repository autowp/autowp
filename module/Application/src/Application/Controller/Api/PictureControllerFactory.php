<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PictureControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        return new PictureController(
            $hydrators->get(\Application\Hydrator\Api\PictureHydrator::class),
            $container->get(\Application\Model\PictureItem::class),
            $container->get(\Application\DuplicateFinder::class),
            $container->get(\Application\Model\UserPicture::class),
            $container->get(\Application\Model\Log::class),
            $container->get(\Application\HostManager::class),
            $container->get(\Application\Service\TelegramService::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Application\Model\CarOfDay::class),
            $filters->get('api_picture_item'),
            $filters->get('api_picture_list'),
            $filters->get('api_picture_edit'),
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get(\Application\Model\PictureModerVote::class),
            $container->get(\Application\Model\DbTable\Picture::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Picture::class)
        );
    }
}
