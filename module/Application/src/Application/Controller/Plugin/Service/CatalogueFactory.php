<?php

namespace Application\Controller\Plugin\Service;

use Application\Controller\Plugin\Catalogue as Plugin;
use Application\Model\Catalogue;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CatalogueFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Plugin
    {
        return new Plugin(
            $container->get(Catalogue::class)
        );
    }
}
