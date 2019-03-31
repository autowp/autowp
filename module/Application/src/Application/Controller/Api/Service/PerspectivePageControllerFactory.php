<?php

namespace Application\Controller\Api\Service;

use Application\Hydrator\Api\PerspectivePageHydrator;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\PerspectivePageController as Controller;

class PerspectivePageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Controller
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        $tables = $container->get('TableManager');
        return new Controller(
            $hydrators->get(PerspectivePageHydrator::class),
            $filters->get('api_perspective_page_list'),
            $tables->get('perspectives_pages')
        );
    }
}
