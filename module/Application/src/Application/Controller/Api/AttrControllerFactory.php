<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AttrControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        $tables = $container->get('TableManager');
        return new AttrController(
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Autowp\User\Model\User::class),
            $hydrators->get(\Application\Hydrator\Api\AttrConflictHydrator::class),
            $hydrators->get(\Application\Hydrator\Api\AttrUserValueHydrator::class),
            $hydrators->get(\Application\Hydrator\Api\AttrAttributeHydrator::class),
            $hydrators->get(\Application\Hydrator\Api\AttrValueHydrator::class),
            $filters->get('api_attr_conflict_get'),
            $filters->get('api_attr_user_value_get'),
            $filters->get('api_attr_user_value_patch_query'),
            $filters->get('api_attr_user_value_patch_data'),
            $filters->get('api_attr_attribute_get'),
            $filters->get('api_attr_value_get'),
            $filters->get('api_attr_attribute_item_patch'),
            $tables->get('attrs_zones')
        );
    }
}
