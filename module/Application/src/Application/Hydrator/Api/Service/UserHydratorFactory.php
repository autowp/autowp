<?php

namespace Application\Hydrator\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Hydrator\Api\UserHydrator as Hydrator;

class UserHydratorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Hydrator(
            $container->get('HttpRouter'),
            $container->get(\Zend\Permissions\Acl\Acl::class)
        );
    }
}
