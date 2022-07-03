<?php

declare(strict_types=1);

namespace Application\Command;

use Application\Model\Brand;
use Autowp\Image\Storage;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BuildBrandsSpriteCommandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(
        containerinterface $container,
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
