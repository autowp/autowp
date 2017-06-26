<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ItemParentLanguageControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');

        return new ItemParentLanguageController(
            $tables->get('item_parent_language'),
            $hydrators->get(\Application\Hydrator\Api\ItemParentLanguageHydrator::class),
            $container->get(\Application\Model\BrandVehicle::class),
            $filters->get('api_item_parent_language_put')
        );
    }
}
