<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Controller\Api\PageController as Controller;
use interop\container\containerinterface;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Controller
    {
        $filters = $container->get('InputFilterManager');
        return new Controller(
            $container->get(AdapterInterface::class),
            $filters->get('api_page_put'),
            $filters->get('api_page_post')
        );
    }
}
