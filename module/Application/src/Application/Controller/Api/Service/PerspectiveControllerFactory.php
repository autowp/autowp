<?php

namespace Application\Controller\Api\Service;

use Application\Controller\Api\PerspectiveController as Controller;
use Application\Hydrator\Api\PerspectiveHydrator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PerspectiveControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Controller
    {
        $hydrators = $container->get('HydratorManager');
        $tables    = $container->get('TableManager');
        return new Controller(
            $hydrators->get(PerspectiveHydrator::class),
            $tables->get('perspectives')
        );
    }
}
