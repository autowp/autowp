<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class BrandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Brand
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Brand(
            $container->get(Item::class)
        );
    }
}
