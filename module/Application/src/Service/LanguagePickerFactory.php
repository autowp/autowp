<?php

namespace Application\Service;

use Application\LanguagePicker;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LanguagePickerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): LanguagePicker
    {
        return new LanguagePicker(
            $container->get('Request'),
            $container->get('Config')['hosts']
        );
    }
}
