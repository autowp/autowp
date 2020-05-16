<?php

namespace Application\Controller\Plugin\Service;

use Application\Controller\Plugin\FileSize as Plugin;
use Application\FileSize;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FileSizeFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Plugin
    {
        return new Plugin($container->get(FileSize::class));
    }
}
