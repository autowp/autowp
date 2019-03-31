<?php

namespace Application\Model\Service;

use Application\Model\Catalogue;
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
        $tables = $container->get('TableManager');
        return new Catalogue(
            $container->get(ItemParent::class),
            $tables->get('item')
        );
    }
}
