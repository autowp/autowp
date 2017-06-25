<?php

namespace Application\Db;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TableManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new TableManager(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class)
        );
    }
}