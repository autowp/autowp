<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemHydrator;
use Application\Hydrator\Api\UserHydrator;
use Application\Model\Brand;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Categories;
use Application\Model\Item;
use Application\Model\Twins;
use Application\Service\SpecificationsService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): IndexController
    {
        $hydrators = $container->get('HydratorManager');
        return new IndexController(
            $container->get('fastCache'),
            $container->get(Brand::class),
            $container->get(Item::class),
            $container->get(Categories::class),
            $container->get(Twins::class),
            $container->get(SpecificationsService::class),
            $container->get(User::class),
            $container->get(CarOfDay::class),
            $container->get(Catalogue::class),
            $hydrators->get(ItemHydrator::class),
            $hydrators->get(UserHydrator::class),
            $container->get('HttpRouter')
        );
    }
}
