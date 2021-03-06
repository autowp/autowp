<?php

namespace Application\Controller\Plugin\Service;

use Application\Controller\Plugin\Translate as Plugin;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TranslateFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Plugin
    {
        return new Plugin(
            $container->get('MvcTranslator')
        );
    }
}
