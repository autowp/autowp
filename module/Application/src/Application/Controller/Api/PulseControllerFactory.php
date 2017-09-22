<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PulseControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        $hydrators = $container->get('HydratorManager');
        return new PulseController(
            $tables->get('log_events'),
            $container->get(\Autowp\User\Model\User::class),
            $hydrators->get(\Application\Hydrator\Api\UserHydrator::class)
        );
    }
}
