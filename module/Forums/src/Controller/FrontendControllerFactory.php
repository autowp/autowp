<?php

namespace Autowp\Forums\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FrontendControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new FrontendController(
            $container->get(\Autowp\Forums\Forums::class),
            $container->get('ForumsTopicNewForm'),
            $container->get('CommentForm'),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Application\Comments::class)
        );
    }
}
