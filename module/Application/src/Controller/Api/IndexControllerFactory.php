<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemHydrator;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Service\SpecificationsService;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): IndexController
    {
        $hydrators = $container->get('HydratorManager');
        return new IndexController(
            $container->get('fastCache'),
            $container->get(Item::class),
            $container->get(SpecificationsService::class),
            $container->get(CarOfDay::class),
            $container->get(Catalogue::class),
            $hydrators->get(ItemHydrator::class)
        );
    }
}
