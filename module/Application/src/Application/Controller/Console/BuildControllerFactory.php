<?php

namespace Application\Controller\Console;

use Application\Model\Brand;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class BuildControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return BuildController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new BuildController(
            $container->get(Brand::class)
        );
    }
}
