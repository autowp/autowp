<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\EventManager\EventManager;
use Zend\ServiceManager\Factory\FactoryInterface;

class CronEventManagerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return EventManager
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new EventManager();
    }
}
