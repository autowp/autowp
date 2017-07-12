<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\CarsController as Controller;

class CarsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('MvcTranslator'),
            $container->get(\Application\Model\BrandVehicle::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\PictureItem::class),
            $container->get(\Application\Model\UserItemSubscribe::class)
        );
    }
}
