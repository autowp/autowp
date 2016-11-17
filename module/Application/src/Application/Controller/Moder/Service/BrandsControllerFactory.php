<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\BrandsController as Controller;

class BrandsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get('ModerBrandEdit'),
            $container->get('BrandLogoForm'),
            $container->get('DescriptionForm'),
            $container->get('ModerBrandEdit'),
            $container->get(\Application\Model\Message::class)
        );
    }
}
