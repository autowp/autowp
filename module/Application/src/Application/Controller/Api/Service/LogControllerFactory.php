<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class LogControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        return new \Application\Controller\Api\LogController(
            $container->get(\Application\Model\Log::class),
            $hydrators->get(\Application\Hydrator\Api\LogHydrator::class),
            $filters->get('api_log_list')
        );
    }
}
