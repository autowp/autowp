<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MapControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new MapController(
            $container->get(\Application\ItemNameFormatter::class),
            $container->get(\Application\Model\Picture::class),
            $tables->get('item')
        );
    }
}
