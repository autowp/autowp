<?php

namespace Application\Hydrator\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Hydrator\Api\CommentHydrator as Hydrator;

class CommentHydratorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Hydrator(
            $container->get('HydratorManager'),
            $container->get(\Application\Comments::class),
            $container->get('HttpRouter')
        );
    }
}
