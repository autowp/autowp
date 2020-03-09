<?php

namespace Application\Controller\Console;

use Application\Model\Brand;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BuildControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): BuildController
    {
        return new BuildController(
            $container->get(Brand::class)
        );
    }
}
