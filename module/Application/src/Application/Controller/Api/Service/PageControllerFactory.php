<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\Db\Adapter\AdapterInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\PageController as Controller;

class PageControllerFactory implements FactoryInterface
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
        $filters = $container->get('InputFilterManager');
        return new Controller(
            $container->get(AdapterInterface::class),
            $filters->get('api_page_put'),
            $filters->get('api_page_post')
        );
    }
}
