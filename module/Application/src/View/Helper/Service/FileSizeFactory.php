<?php

declare(strict_types=1);

namespace Application\View\Helper\Service;

use Application\FileSize;
use Application\View\Helper\FileSize as Helper;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FileSizeFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(FileSize::class)
        );
    }
}
