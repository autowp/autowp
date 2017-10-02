<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ForumControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        return new ForumController(
            $container->get(\Autowp\Forums\Forums::class),
            $container->get(\Autowp\User\Model\User::class),
            $hydrators->get(\Application\Hydrator\Api\ForumThemeHydrator::class),
            $hydrators->get(\Application\Hydrator\Api\ForumTopicHydrator::class),
            $filters->get('api_forum_theme_list'),
            $filters->get('api_forum_theme_get'),
            $filters->get('api_forum_topic_get'),
            $filters->get('api_forum_topic_put'),
            $filters->get('api_forum_topic_post')
        );
    }
}
