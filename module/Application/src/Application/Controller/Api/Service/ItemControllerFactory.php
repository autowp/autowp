<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\ItemController as Controller;

class ItemControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        return new Controller(
            $hydrators->get(\Application\Hydrator\Api\ItemHydrator::class),
            $container->get(\Application\ItemNameFormatter::class),
            $filters->get('api_item_list')
        );
    }
}
