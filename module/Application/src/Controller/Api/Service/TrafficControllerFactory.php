<?php

namespace Application\Controller\Api\Service;

use Application\Controller\Api\TrafficController as Controller;
use Application\Hydrator\Api\TrafficHydrator;
use Autowp\Traffic\TrafficControl;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TrafficControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Controller
    {
        $hydrators = $container->get('HydratorManager');
        return new Controller(
            $container->get(TrafficControl::class),
            $hydrators->get(TrafficHydrator::class)
        );
    }
}
