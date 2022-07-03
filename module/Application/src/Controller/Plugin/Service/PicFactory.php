<?php

declare(strict_types=1);

namespace Application\Controller\Plugin\Service;

use Application\Controller\Plugin\Pic as Plugin;
use Application\Model\Picture;
use Application\PictureNameFormatter;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PicFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Plugin
    {
        return new Plugin(
            $container->get(PictureNameFormatter::class),
            $container->get(Picture::class)
        );
    }
}
