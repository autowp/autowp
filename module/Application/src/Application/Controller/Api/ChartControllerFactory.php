<?php

namespace Application\Controller\Api;

use Application\Service\SpecificationsService;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ChartControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return ChartController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new ChartController(
            $container->get(SpecificationsService::class),
            $tables->get('spec'),
            $tables->get('attrs_attributes')
        );
    }
}
