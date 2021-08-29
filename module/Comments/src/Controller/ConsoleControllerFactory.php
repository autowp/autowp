<?php

declare(strict_types=1);

namespace Autowp\Comments\Controller;

use Autowp\Comments\CommentsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConsoleControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConsoleController
    {
        return new ConsoleController(
            $container->get(CommentsService::class)
        );
    }
}
