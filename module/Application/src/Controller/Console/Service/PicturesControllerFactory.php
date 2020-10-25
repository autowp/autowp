<?php

namespace Application\Controller\Console\Service;

use Application\Controller\Console\PicturesController as Controller;
use Application\DuplicateFinder;
use Application\Model\Picture;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PicturesControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Controller
    {
        return new Controller(
            $container->get(Picture::class),
            $container->get(DuplicateFinder::class),
            $container->get(Storage::class)
        );
    }
}
