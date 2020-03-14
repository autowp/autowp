<?php

namespace Application\Controller\Api;

use Application\Model\Brand;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConfigControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConfigController
    {
        return new ConfigController(
            $container->get(Brand::class)
        );
    }
}
