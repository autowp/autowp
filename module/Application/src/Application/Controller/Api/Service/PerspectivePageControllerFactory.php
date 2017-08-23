<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\PerspectivePageController as Controller;

class PerspectivePageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        $tables = $container->get('TableManager');
        return new Controller(
            $hydrators->get(\Application\Hydrator\Api\PerspectivePageHydrator::class),
            $filters->get('api_perspective_page_list'),
            $tables->get('perspectives_pages')
        );
    }
}
