<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemHydrator;
use Application\Hydrator\Api\PictureHydrator;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class NewControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return NewController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        $hydrators = $container->get('HydratorManager');

        return new NewController(
            $container->get(Picture::class),
            $container->get(Item::class),
            $container->get(PictureItem::class),
            $filters->get('api_new_get'),
            $hydrators->get(PictureHydrator::class),
            $hydrators->get(PictureHydrator::class),
            $hydrators->get(ItemHydrator::class)
        );
    }
}
