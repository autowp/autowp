<?php

namespace Application\Controller\Api\Service;

use Application\Controller\Api\IpController as Controller;
use Application\Hydrator\Api\IpHydrator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IpControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Controller
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        return new Controller(
            $hydrators->get(IpHydrator::class),
            $filters->get('api_ip_item')
        );
    }
}
