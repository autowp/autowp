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
        return new AttrController(
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Autowp\User\Model\User::class),
            $hydrators->get(\Application\Hydrator\Api\AttrConflictHydrator::class),
            $hydrators->get(\Application\Hydrator\Api\AttrUserValueHydrator::class),
            $filters->get('api_attr_conflict_get'),
            $filters->get('api_attr_user_value_get'),
            $filters->get('api_attr_user_value_patch_query'),
            $filters->get('api_attr_user_value_patch_data')
        );
    }
}
