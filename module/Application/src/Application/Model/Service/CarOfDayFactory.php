<?php

namespace Application\Model\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CarOfDayFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new \Application\Model\CarOfDay(
            $container->get(\Application\ItemNameFormatter::class),
            $container->get(\Autowp\Image\Storage::class),
            $container->get(\Application\Model\Catalogue::class),
            $container->get('HttpRouter'),
            $container->get('MvcTranslator'),
            $container->get(\Application\Service\SpecificationsService::class)
        );
    }
}
