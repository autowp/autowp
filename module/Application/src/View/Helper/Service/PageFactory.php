<?php

declare(strict_types=1);

namespace Application\View\Helper\Service;

use Application\View\Helper\Page as Helper;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PageFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Helper
    {
        $tables = $container->get('TableManager');
        return new Helper(
            $tables->get('pages')
        );
    }
}
