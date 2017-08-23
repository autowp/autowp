<?php

namespace Application\Router\Http;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CatalogueFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $options['itemParent'] = $container->get(\Application\Model\ItemParent::class);
        $tables = $container->get('TableManager');
        $options['itemTable'] = $tables->get('item');
        return new Catalogue($options);
    }
}
