<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemHydrator;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\PictureNameFormatter;
use Application\Service\Mosts;
use Autowp\TextStorage\Service;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MostsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MostsController
    {
        $hydrators = $container->get('HydratorManager');
        return new MostsController(
            $container->get(Service::class),
            $container->get(Item::class),
            $container->get(Perspective::class),
            $container->get(Mosts::class),
            $container->get(Picture::class),
            $hydrators->get(ItemHydrator::class),
            $container->get(PictureNameFormatter::class)
        );
    }
}
