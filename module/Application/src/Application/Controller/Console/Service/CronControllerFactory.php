<?php

namespace Application\Controller\Console\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Console\CronController as Controller;

class CronControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('Application'),
            $container->get('CronEventManager')
        );
    }
}
