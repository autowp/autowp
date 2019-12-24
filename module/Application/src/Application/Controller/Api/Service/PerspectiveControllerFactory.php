<?php

namespace Application\Controller\Api\Service;

use Application\Hydrator\Api\PerspectiveHydrator;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\Api\PerspectiveController as Controller;

class PerspectiveControllerFactory implements FactoryInterface
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
        $tables = $container->get('TableManager');
        return new Controller(
            $hydrators->get(PerspectiveHydrator::class),
            $tables->get('perspectives')
        );
    }
}
