<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CommentControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        $tables = $container->get('TableManager');
        return new CommentController(
            $container->get(\Application\Comments::class),
            $hydrators->get(\Application\Hydrator\Api\CommentHydrator::class),
            $tables->get('users'),
            $filters->get('api_comments_get'),
            $filters->get('api_comments_get_public'),
            $filters->get('api_comments_post'),
            $filters->get('api_comments_put'),
            $filters->get('api_comments_item_get'),
            $container->get(\Autowp\User\Model\User::class),
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Autowp\Votings\Votings::class),
            $tables->get('articles')
        );
    }
}
