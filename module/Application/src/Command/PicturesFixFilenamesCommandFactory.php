<?php

declare(strict_types=1);

namespace Application\Command;

use Application\Model\Picture;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PicturesFixFilenamesCommandFactory implements FactoryInterface
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
    ): PicturesFixFilenamesCommand {
        return new PicturesFixFilenamesCommand(
            'pictures-fix-filenames',
            $container->get(Picture::class),
            $container->get(Storage::class)
        );
    }
}