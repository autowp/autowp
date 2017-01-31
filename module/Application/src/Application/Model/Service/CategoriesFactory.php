<?php

namespace Application\Model\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CategoriesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new \Application\Model\Categories(
            $container->get('HttpRouter'),
            $container->get(\Zend\Db\Adapter\AdapterInterface::class)
        );
    }
}
