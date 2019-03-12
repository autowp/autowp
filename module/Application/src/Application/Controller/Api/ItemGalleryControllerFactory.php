<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ItemGalleryControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');

        return new ItemGalleryController(
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\PictureItem::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get(\Application\PictureNameFormatter::class),
            $container->get(\Application\ItemNameFormatter::class)
        );
    }
}
