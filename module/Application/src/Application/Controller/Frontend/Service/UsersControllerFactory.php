<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\UsersController as Controller;

class UsersControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('longCache'),
            $container->get(\Autowp\Traffic\TrafficControl::class),
            $container->get(\Autowp\Comments\CommentsService::class)
        );
    }
}
