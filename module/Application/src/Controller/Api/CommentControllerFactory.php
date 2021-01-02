<?php

namespace Application\Controller\Api;

use Application\Comments;
use Application\HostManager;
use Application\Hydrator\Api\CommentHydrator;
use Application\Model\Item;
use Application\Model\Picture;
use Autowp\Forums\Forums;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Autowp\Votings\Votings;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): CommentController
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        $tables    = $container->get('TableManager');
        return new CommentController(
            $container->get(Comments::class),
            $hydrators->get(CommentHydrator::class),
            $tables->get('users'),
            $filters->get('api_comments_get'),
            $filters->get('api_comments_get_public'),
            $filters->get('api_comments_post'),
            $filters->get('api_comments_put'),
            $filters->get('api_comments_item_get'),
            $container->get(User::class),
            $container->get(HostManager::class),
            $container->get(MessageService::class),
            $container->get(Picture::class),
            $container->get(Item::class),
            $container->get(Votings::class),
            $tables->get('articles'),
            $container->get(Forums::class)
        );
    }
}
