<?php

namespace Application\Controller\Api;

use Application\Model\Item;
use Application\Model\Picture;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class StatControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): StatController
    {
        return new StatController(
            $container->get(Item::class),
            $container->get(Picture::class)
        );
    }
}
