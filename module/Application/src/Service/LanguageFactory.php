<?php

declare(strict_types=1);

namespace Application\Service;

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
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Language
    {
        $config = $container->get('Config');
        return new Language(
            $container->get('Request'),
            $config['hosts']
        );
    }
}
