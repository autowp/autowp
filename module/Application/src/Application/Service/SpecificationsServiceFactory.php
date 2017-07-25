<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Service\SpecificationsService;

class SpecificationsServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new SpecificationsService(
            $container->get('MvcTranslator'),
            $container->get(\Application\ItemNameFormatter::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\ItemParent::class),
            $container->get(\Application\Model\DbTable\Picture::class),
            $container->get(\Application\Model\VehicleType::class),
            $tables->get('attrs_units'),
            $tables->get('attrs_list_options'),
            $tables->get('attrs_types'),
            $tables->get('attrs_attributes'),
            $tables->get('attrs_zone_attributes')
        );
    }
}
