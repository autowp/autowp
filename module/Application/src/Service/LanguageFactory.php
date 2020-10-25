<?php

namespace Application\Service;

use Application\Language;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LanguageFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Language
    {
        $config = $container->get('Config');
        return new Language(
            $container->get('Request'),
            $config['hosts']
        );
    }
}
