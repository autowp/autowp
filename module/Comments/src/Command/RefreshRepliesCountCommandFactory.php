<?php

declare(strict_types=1);

namespace Autowp\Comments\Command;

use Autowp\Comments\CommentsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class RefreshRepliesCountCommandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): RefreshRepliesCountCommand {
        return new RefreshRepliesCountCommand(
            'refresh-replies-count',
            $container->get(CommentsService::class)
        );
    }
}
