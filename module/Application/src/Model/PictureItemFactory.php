<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureItemFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): PictureItem
    {
        $tables = $container->get('TableManager');
        return new PictureItem(
            $tables->get('picture_item'),
            $tables->get('item'),
            $tables->get('pictures')
        );
    }
}
