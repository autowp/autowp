<?php

namespace Application\Controller\Api;

use Application\ItemNameFormatter;
use Application\Model\Picture;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MapControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MapController
    {
        $tables = $container->get('TableManager');
        return new MapController(
            $container->get(ItemNameFormatter::class),
            $container->get(Picture::class),
            $tables->get('item')
        );
    }
}
