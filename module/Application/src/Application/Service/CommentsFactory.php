<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CommentsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new \Application\Comments(
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get('HttpRouter')
        );
    }
}
