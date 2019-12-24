<?php

namespace Application\Controller\Plugin\Service;

use Application\FileSize;
use Application\Language;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\Plugin\FileSize as Plugin;

class FileSizeFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Plugin
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Plugin(
            $container->get(Language::class),
            $container->get(FileSize::class)
        );
    }
}
