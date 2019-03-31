<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TwinsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Twins
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Twins(
            $container->get(Picture::class),
            $container->get(Item::class),
            $container->get(Brand::class)
        );
    }
}
