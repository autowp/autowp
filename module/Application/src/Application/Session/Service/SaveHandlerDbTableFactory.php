<?php

namespace Application\Session\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Session\SaveHandler\DbTable as SaveHandlerDbTable;

use Zend_Db_Adapter_Abstract;

class SaveHandlerDbTableFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new SaveHandlerDbTable([
            'table' => [
                'db'      => $container->get(Zend_Db_Adapter_Abstract::class),
                'name'    => 'session',
                'primary' => ['id']
            ]
        ]);
    }
}
