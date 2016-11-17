<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\LanguagePicker;

class LanguagePickerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new LanguagePicker(
            $container->get('Request'),
            $container->get('Config')['hosts']
        );
    }
}
