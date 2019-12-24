<?php

namespace Application\Controller\Api\Service;

use Application\Hydrator\Api\TrafficHydrator;
use Autowp\Traffic\TrafficControl;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\Api\TrafficController as Controller;

class TrafficControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Controller
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        return new Controller(
            $container->get(TrafficControl::class),
            $hydrators->get(TrafficHydrator::class)
        );
    }
}
