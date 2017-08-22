<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\UploadController as Controller;

class UploadControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('ViewHelperManager')->get('partial'),
            $container->get(\Application\Model\PictureItem::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Model\ItemParent::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Brand::class),
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Service\PictureService::class)
        );
    }
}
