<?php

namespace Application\Session\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Session\SaveHandler\Cache;

class SaveHandlerCacheFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Cache
    {
        return new Cache($container->get('sessionCache'));
    }
}
