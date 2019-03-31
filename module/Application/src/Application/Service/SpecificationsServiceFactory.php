<?php

namespace Application\Service;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SpecificationsServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return SpecificationsService
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new SpecificationsService(
            $container->get('MvcTranslator'),
            $container->get(ItemNameFormatter::class),
            $container->get(Item::class),
            $container->get(ItemParent::class),
            $container->get(Picture::class),
            $container->get(VehicleType::class),
            $container->get(User::class),
            $tables->get('attrs_units'),
            $tables->get('attrs_list_options'),
            $tables->get('attrs_types'),
            $tables->get('attrs_attributes'),
            $tables->get('attrs_zone_attributes'),
            $tables->get('attrs_user_values'),
            $tables->get('attrs_user_values_float'),
            $tables->get('attrs_user_values_int'),
            $tables->get('attrs_user_values_list'),
            $tables->get('attrs_user_values_string'),
            $tables->get('attrs_values'),
            $tables->get('attrs_values_float'),
            $tables->get('attrs_values_int'),
            $tables->get('attrs_values_list'),
            $tables->get('attrs_values_string')
        );
    }
}
