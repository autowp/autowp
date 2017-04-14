<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\PicturesController as Controller;

class PicturesControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\Model\DbTable\Picture::class),
            $container->get('BanForm'),
            $container->get(\Autowp\Traffic\TrafficControl::class)
        );
    }
}
