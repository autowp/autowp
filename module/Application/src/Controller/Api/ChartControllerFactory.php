<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Service\SpecificationsService;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ChartControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): ChartController
    {
        $tables = $container->get('TableManager');
        return new ChartController(
            $container->get(SpecificationsService::class),
            $tables->get('spec'),
            $tables->get('attrs_attributes')
        );
    }
}
