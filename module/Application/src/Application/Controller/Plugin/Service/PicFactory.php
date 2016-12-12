<?php

namespace Application\Controller\Plugin\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Plugin\Pic as Plugin;

class PicFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Plugin(
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get('MvcTranslator'),
            $container->get(\Application\PictureNameFormatter::class),
            $container->get(\Application\VehicleNameFormatter::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\PictureItem::class),
            $container->get('HttpRouter')
        );
    }
}
