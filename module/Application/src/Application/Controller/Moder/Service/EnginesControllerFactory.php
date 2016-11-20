<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\EnginesController as Controller;

class EnginesControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('ModerFactoryFilterForm'),
            $container->get('ModerEngineForm'),
            $container->get(\Application\Service\SpecificationsService::class)
        );
    }
}
