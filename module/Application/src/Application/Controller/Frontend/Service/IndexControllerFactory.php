<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\IndexController as Controller;

class IndexControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('fastCache'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\CarOfDay::class),
            $container->get(\Application\Model\Categories::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Model\Twins::class),
            $container->get(\Application\Model\DbTable\Picture::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Brand::class)
        );
    }
}
