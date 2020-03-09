<?php

namespace Application\View\Helper\Service;

use Application\Language;
use Application\View\Helper\PageEnv as Helper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PageEnvFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        $tables = $container->get('TableManager');
        return new Helper(
            $container->get(Language::class),
            $tables->get('pages')
        );
    }
}
