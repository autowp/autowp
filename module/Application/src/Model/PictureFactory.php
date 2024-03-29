<?php

declare(strict_types=1);

namespace Application\Model;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Picture
    {
        $tables = $container->get('TableManager');
        return new Picture(
            $tables->get('pictures'),
            $tables->get('item'),
            $container->get(PictureModerVote::class),
            $tables->get('picture_item'),
            $container->get(Perspective::class)
        );
    }
}
