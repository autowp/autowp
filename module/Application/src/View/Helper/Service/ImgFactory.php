<?php

declare(strict_types=1);

namespace Application\View\Helper\Service;

use Application\View\Helper\Img as Helper;
use Autowp\Image\Storage;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ImgFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(Storage::class)
        );
    }
}
