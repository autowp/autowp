<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Comments;
use Application\Hydrator\Api\CommentHydrator;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): CommentController
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
            $filters->get('api_comments_item_get')
        );
    }
}
