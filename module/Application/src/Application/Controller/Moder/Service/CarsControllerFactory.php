<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\CarsController as Controller;

class CarsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        return new Controller(
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get('MvcTranslator'),
            clone $container->get('DescriptionForm'),
            clone $container->get('DescriptionForm'),
            $container->get('ModerCarParent'),
            $container->get('ModerCarsFilter'),
            $container->get('BrandLogoForm'),
            $container->get(\Application\Model\BrandVehicle::class),
            $container->get(\Application\Model\Message::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\PictureItem::class),
            $config['content_languages']
        );
    }
}
