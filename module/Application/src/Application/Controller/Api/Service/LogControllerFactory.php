<?php

namespace Application\Controller\Api\Service;

use Application\Controller\Api\LogController;
use Application\Hydrator\Api\LogHydrator;
use Application\Model\Log;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class LogControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return LogController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        return new LogController(
            $container->get(Log::class),
            $hydrators->get(LogHydrator::class),
            $filters->get('api_log_list')
        );
    }
}
