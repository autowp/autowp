<?php

namespace Application\Controller\Frontend\Service;

use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Service\Mosts;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\CatalogueController as Controller;

class CatalogueControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Controller
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        return new Controller(
            $config['mosts_min_vehicles_count'],
            $container->get(Item::class),
            $container->get(Mosts::class),
            $container->get(Picture::class),
            $container->get(Brand::class)
        );
    }
}
