<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MostsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        return new MostsController(
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Service\Mosts::class),
            $container->get(\Application\Model\Picture::class),
            $hydrators->get(\Application\Hydrator\Api\ItemHydrator::class),
            $container->get(\Application\PictureNameFormatter::class)
        );
    }
}
