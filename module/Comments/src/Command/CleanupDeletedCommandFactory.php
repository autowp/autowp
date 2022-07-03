<?php

declare(strict_types=1);

namespace Autowp\Comments\Command;

use Autowp\Comments\CommentsService;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CleanupDeletedCommandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): CleanupDeletedCommand {
        return new CleanupDeletedCommand(
            'cleanup-deleted',
            $container->get(CommentsService::class)
        );
    }
}
