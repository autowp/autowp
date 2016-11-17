<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\FactoryController as Controller;

class FactoryControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get('ModerFactoryAddForm'),
            $container->get('ModerFactoryEditForm'),
            $container->get('DescriptionForm'),
            $container->get('ModerFactoryFilterForm'),
            $container->get(\Application\Model\Message::class)
        );
    }
}
