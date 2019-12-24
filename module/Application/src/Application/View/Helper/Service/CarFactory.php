<?php

namespace Application\View\Helper\Service;

use Application\ItemNameFormatter;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\View\Helper\Car as Helper;

class CarFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Helper
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Helper(
            $container->get(ItemNameFormatter::class)
        );
    }
}
