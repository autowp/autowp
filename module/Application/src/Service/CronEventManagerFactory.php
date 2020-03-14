<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Laminas\EventManager\EventManager;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CronEventManagerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): EventManager
    {
        return new EventManager();
    }
}
