<?php

namespace Autowp\Comments\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConsoleControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ConsoleController(
            $container->get(\Autowp\Comments\CommentsService::class)
        );
    }
}
