<?php

namespace Application\View\Helper\Service;

use Application\LanguagePicker;
use Application\View\Helper\LanguagePicker as Helper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LanguagePickerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(LanguagePicker::class)
        );
    }
}
