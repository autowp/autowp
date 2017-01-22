<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CommentsController as Controller;

class CommentsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\HostManager::class),
            $container->get('CommentForm'),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Autowp\Comments\CommentsService::class)
        );
    }
}
