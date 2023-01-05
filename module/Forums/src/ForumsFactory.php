<?php

declare(strict_types=1);

namespace Autowp\Forums;

use Autowp\Comments\CommentsService;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ForumsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Forums
    {
        $tables = $container->get('TableManager');
        return new Forums(
            $container->get(CommentsService::class),
            $tables->get('forums_themes'),
            $tables->get('forums_topics')
        );
    }
}
