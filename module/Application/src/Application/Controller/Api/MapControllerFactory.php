<?php

namespace Application\Controller\Api;

use Application\ItemNameFormatter;
use Application\Model\Picture;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MapControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return MapController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new MapController(
            $container->get(ItemNameFormatter::class),
            $container->get(Picture::class),
            $tables->get('item')
        );
    }
}
