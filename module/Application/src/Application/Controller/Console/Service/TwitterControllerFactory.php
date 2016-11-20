<?php

namespace Application\Controller\Console\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Console\TwitterController as Controller;

class TwitterControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('Config')['twitter'],
            $container->get(\Application\Model\CarOfDay::class)
        );
    }
}
