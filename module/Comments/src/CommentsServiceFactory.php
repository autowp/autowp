<?php

namespace Autowp\Comments;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CommentsServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new CommentsService(
            $tables->get('comment_vote'),
            $tables->get('comment_topic'),
            $tables->get('comment_message'),
            $tables->get('comment_topic_view'),
            $tables->get('comment_topic_subscribe')
        );
    }
}
