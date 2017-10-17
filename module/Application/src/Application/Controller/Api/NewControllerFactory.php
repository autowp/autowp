<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class NewControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        $hydrators = $container->get('HydratorManager');

        return new NewController(
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\PictureItem::class),
            $filters->get('api_new_get'),
            $hydrators->get(\Application\Hydrator\Api\PictureHydrator::class),
            $hydrators->get(\Application\Hydrator\Api\PictureHydrator::class),
            $hydrators->get(\Application\Hydrator\Api\ItemHydrator::class)
        );
    }
}
