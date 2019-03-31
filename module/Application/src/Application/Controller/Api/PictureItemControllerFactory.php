<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\PictureItemHydrator;
use Application\Model\Item;
use Application\Model\Log;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PictureItemControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return PictureItemController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');

        return new PictureItemController(
            $container->get(PictureItem::class),
            $container->get(Log::class),
            $hydrators->get(PictureItemHydrator::class),
            $filters->get('api_picture_item_list'),
            $filters->get('api_picture_item_item'),
            $container->get(Item::class),
            $container->get(Picture::class)
        );
    }
}
