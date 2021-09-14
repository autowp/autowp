<?php

declare(strict_types=1);

namespace Application\Command;

use Application\Model\Brand;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BuildBrandsSpriteCommandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): BuildBrandsSpriteCommand {
        $config = $container->get('Config')['fileStorage'];
        return new BuildBrandsSpriteCommand(
            'build-brands-sprite',
            $container->get(Brand::class),
            $config,
            $container->get(Storage::class)
        );
    }
}
