<?php

namespace Application\Session\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Session\SaveHandler\Cache;

class SaveHandlerCacheFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Cache($container->get('sessionCache'));
    }
}
