<?php

declare(strict_types=1);

namespace Autowp\Comments;

use Autowp\User\Model\User;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentsServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): CommentsService
    {
        $tables = $container->get('TableManager');
        return new CommentsService(
            $tables->get('comment_vote'),
            $tables->get('comment_topic'),
            $tables->get('comment_message'),
            $tables->get('comment_topic_view'),
            $tables->get('comment_topic_subscribe'),
            $container->get(User::class)
        );
    }
}
