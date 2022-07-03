<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Hydrator\Api\LogHydrator;
use Application\Model\Log;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LogControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): LogController
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        return new LogController(
            $container->get(Log::class),
            $hydrators->get(LogHydrator::class),
            $filters->get('api_log_list')
        );
    }
}
