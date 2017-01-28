<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\ForumsController as Controller;

class ForumsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Autowp\Forums\Forums::class),
            $container->get('ForumsTopicNewForm'),
            $container->get('CommentForm'),
            $container->get(\Zend\Mail\Transport\TransportInterface::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Autowp\Comments\CommentsService::class)
        );
    }
}
