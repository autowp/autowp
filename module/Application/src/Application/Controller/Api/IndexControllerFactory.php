<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        return new IndexController(
            $container->get('fastCache'),
            $container->get(\Application\Model\Brand::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Categories::class),
            $container->get(\Application\Model\Twins::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Autowp\User\Model\User::class),
            $container->get(\Application\Model\CarOfDay::class),
            $container->get(\Application\Model\Catalogue::class),
            $hydrators->get(\Application\Hydrator\Api\ItemHydrator::class),
            $hydrators->get(\Application\Hydrator\Api\UserHydrator::class),
            $container->get('HttpRouter')
        );
    }
}
