<?php

namespace Application\Controller\Plugin\Service;

use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\PictureNameFormatter;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\Plugin\Pic as Plugin;

class PicFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Plugin
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Plugin(
            $container->get(PictureNameFormatter::class),
            $container->get(PictureItem::class),
            $container->get(Catalogue::class),
            $container->get(Item::class),
            $container->get(Picture::class)
        );
    }
}
