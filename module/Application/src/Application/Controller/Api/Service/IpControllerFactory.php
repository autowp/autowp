<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\IpController as Controller;

class IpControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        return new Controller(
            $hydrators->get(\Application\Hydrator\Api\IpHydrator::class),
            $filters->get('api_ip_item')
        );
    }
}
