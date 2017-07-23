<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\MostsController as Controller;

class MostsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Service\Mosts::class),
            $container->get(\Application\Model\DbTable\Picture::class)
        );
    }
}
