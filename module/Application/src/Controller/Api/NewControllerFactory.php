<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemHydrator;
use Application\Hydrator\Api\PictureHydrator;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class NewControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): NewController
    {
        $filters   = $container->get('InputFilterManager');
        $hydrators = $container->get('HydratorManager');

        return new NewController(
            $container->get(Picture::class),
            $container->get(Item::class),
            $container->get(PictureItem::class),
            $filters->get('api_new_get'),
            $hydrators->get(PictureHydrator::class),
            $hydrators->get(PictureHydrator::class),
            $hydrators->get(ItemHydrator::class)
        );
    }
}
