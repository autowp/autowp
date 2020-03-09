<?php

namespace Autowp\Comments\Controller;

use Autowp\Comments\CommentsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConsoleControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConsoleController
    {
        return new ConsoleController(
            $container->get(CommentsService::class)
        );
    }
}
