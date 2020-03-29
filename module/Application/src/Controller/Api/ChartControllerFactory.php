<?php

namespace Application\Controller\Api;

use Application\Service\SpecificationsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ChartControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ChartController
    {
        $tables = $container->get('TableManager');
        return new ChartController(
            $container->get(SpecificationsService::class),
            $tables->get('spec'),
            $tables->get('attrs_attributes')
        );
    }
}
