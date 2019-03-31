<?php

namespace Application\Controller\Api;

use Application\Model\Item;
use Application\Model\Picture;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class StatControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return StatController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new StatController(
            $container->get(Item::class),
            $container->get(Picture::class)
        );
    }
}
