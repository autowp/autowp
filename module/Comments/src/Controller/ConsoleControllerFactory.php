<?php

namespace Autowp\Comments\Controller;

use Autowp\Comments\CommentsService;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConsoleControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return ConsoleController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ConsoleController(
            $container->get(CommentsService::class)
        );
    }
}
