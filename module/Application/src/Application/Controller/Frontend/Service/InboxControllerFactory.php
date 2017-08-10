<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\InboxController as Controller;

class InboxControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\Brand::class)
        );
    }
}
