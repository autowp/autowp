<?php

namespace Application\Controller\Api\Service;

use Application\Controller\Api\PerspectivePageController as Controller;
use Application\Hydrator\Api\PerspectivePageHydrator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PerspectivePageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Controller
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        $tables    = $container->get('TableManager');
        return new Controller(
            $hydrators->get(PerspectivePageHydrator::class),
            $filters->get('api_perspective_page_list'),
            $tables->get('perspectives_pages')
        );
    }
}
