<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\PerspectiveController as Controller;

class PerspectiveControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $tables = $container->get('TableManager');
        return new Controller(
            $hydrators->get(\Application\Hydrator\Api\PerspectiveHydrator::class),
            $tables->get('perspectives')
        );
    }
}
