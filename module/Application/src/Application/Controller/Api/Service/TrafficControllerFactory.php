<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\TrafficController as Controller;

class TrafficControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        return new Controller(
            $container->get(\Autowp\Traffic\TrafficControl::class),
            $hydrators->get(\Application\Hydrator\Api\TrafficHydrator::class)
        );
    }
}
