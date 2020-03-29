<?php

namespace Application\View\Helper\Service;

use Application\View\Helper\Page as Helper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PageFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        $tables = $container->get('TableManager');
        return new Helper(
            $tables->get('pages')
        );
    }
}
