<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Hydrator\Api\AttrAttributeHydrator;
use Application\Hydrator\Api\AttrConflictHydrator;
use Application\Hydrator\Api\AttrUserValueHydrator;
use Application\Hydrator\Api\AttrValueHydrator;
use Application\Service\SpecificationsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AttrControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AttrController
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        $tables    = $container->get('TableManager');
        return new AttrController(
            $container->get(SpecificationsService::class),
            $hydrators->get(AttrConflictHydrator::class),
            $hydrators->get(AttrUserValueHydrator::class),
            $hydrators->get(AttrAttributeHydrator::class),
            $hydrators->get(AttrValueHydrator::class),
            $filters->get('api_attr_conflict_get'),
            $filters->get('api_attr_user_value_get'),
            $filters->get('api_attr_user_value_patch_query'),
            $filters->get('api_attr_user_value_patch_data'),
            $filters->get('api_attr_attribute_get'),
            $filters->get('api_attr_attribute_post'),
            $filters->get('api_attr_attribute_item_get'),
            $filters->get('api_attr_value_get'),
            $filters->get('api_attr_attribute_item_patch'),
            $filters->get('api_attr_zone_attribute_get'),
            $filters->get('api_attr_zone_attribute_post'),
            $filters->get('api_attr_list_options_get'),
            $filters->get('api_attr_list_options_post'),
            $tables->get('attrs_zones'),
            $tables->get('attrs_zone_attributes'),
            $tables->get('attrs_types'),
            $tables->get('attrs_list_options')
        );
    }
}
