<?php

namespace Application\Controller\Frontend;

use Application\Model\Brand;
use Application\Model\Picture;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\PictureController as Controller;

class PictureControllerFactory implements FactoryInterface
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
        return new Controller(
            $container->get(Picture::class),
            $container->get(Brand::class)
        );
    }
}
