<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\UserHydrator;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PulseControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return PulseController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        $hydrators = $container->get('HydratorManager');
        return new PulseController(
            $tables->get('log_events'),
            $container->get(User::class),
            $hydrators->get(UserHydrator::class)
        );
    }
}
