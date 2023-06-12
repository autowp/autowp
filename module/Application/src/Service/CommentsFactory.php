<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Comments;
use Autowp\Comments\CommentsService;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Comments
    {
        return new Comments(
            $container->get(CommentsService::class)
        );
    }
}
