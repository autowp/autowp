<?php

declare(strict_types=1);

namespace Application\Controller\Plugin\Service;

use Application\Controller\Plugin\Language as Plugin;
use Application\Language;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LanguageFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Plugin
    {
        return new Plugin(
            $container->get(Language::class)
        );
    }
}
