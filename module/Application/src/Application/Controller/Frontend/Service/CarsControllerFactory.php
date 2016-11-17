<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CarsController as Controller;

class CarsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\HostManager::class),
            $container->get('AttrsLogFilterForm'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\Message::class)
        );
    }
}
