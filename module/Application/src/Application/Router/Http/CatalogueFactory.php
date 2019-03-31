<?php

namespace Application\Router\Http;

use Application\Model\ItemParent;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CatalogueFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Catalogue
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $options['itemParent'] = $container->get(ItemParent::class);
        $tables = $container->get('TableManager');
        $options['itemTable'] = $tables->get('item');
        return new Catalogue($options);
    }
}
