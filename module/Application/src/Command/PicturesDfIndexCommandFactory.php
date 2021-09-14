<?php

declare(strict_types=1);

namespace Application\Command;

use Application\DuplicateFinder;
use Application\Model\Picture;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PicturesDfIndexCommandFactory implements FactoryInterface
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
    ): PicturesDfIndexCommand {
        return new PicturesDfIndexCommand(
            'pictures-fill-point',
            $container->get(Picture::class),
            $container->get(DuplicateFinder::class),
            $container->get(Storage::class)
        );
    }
}
