<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\ChartController as Controller;

class ChartControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Controller(
            $container->get(\Application\Service\SpecificationsService::class),
            $tables->get('spec'),
            $tables->get('attrs_attributes')
        );
    }
}
