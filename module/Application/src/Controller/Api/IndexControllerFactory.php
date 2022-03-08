<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemHydrator;
use Application\Hydrator\Api\UserHydrator;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Service\SpecificationsService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): IndexController
    {
        $hydrators = $container->get('HydratorManager');
        return new IndexController(
            $container->get('fastCache'),
            $container->get(Item::class),
            $container->get(SpecificationsService::class),
            $container->get(User::class),
            $container->get(CarOfDay::class),
            $container->get(Catalogue::class),
            $hydrators->get(ItemHydrator::class),
            $hydrators->get(UserHydrator::class)
        );
    }
}
