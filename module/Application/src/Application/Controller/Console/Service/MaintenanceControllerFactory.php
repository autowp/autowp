<?php

namespace Application\Controller\Console\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Console\MaintenanceController as Controller;

class MaintenanceControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Zend_Db_Adapter_Abstract::class),
            $container->get(\Zend\Session\SessionManager::class)
        );
    }
}
