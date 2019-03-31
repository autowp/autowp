<?php

namespace Application\Session\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Session\SaveHandler\Cache;

class SaveHandlerCacheFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Cache
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Cache($container->get('sessionCache'));
    }
}
