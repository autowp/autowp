<?php

declare(strict_types=1);

namespace Application\Model;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureItemFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): PictureItem
    {
        $tables = $container->get('TableManager');
        return new PictureItem(
            $tables->get('picture_item'),
            $tables->get('item'),
            $tables->get('pictures')
        );
    }
}
