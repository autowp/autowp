<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\ForumThemeHydrator;
use Application\Hydrator\Api\ForumTopicHydrator;
use Autowp\Forums\Forums;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ForumControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ForumController
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        return new ForumController(
            $container->get(Forums::class),
            $container->get(User::class),
            $hydrators->get(ForumThemeHydrator::class),
            $hydrators->get(ForumTopicHydrator::class),
            $filters->get('api_forum_theme_list'),
            $filters->get('api_forum_theme_get'),
            $filters->get('api_forum_topic_list'),
            $filters->get('api_forum_topic_get'),
            $filters->get('api_forum_topic_put'),
            $filters->get('api_forum_topic_post')
        );
    }
}
