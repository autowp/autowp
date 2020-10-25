<?php

namespace Application\Service;

use Application\ItemNameFormatter;
use Application\PictureNameFormatter as Model;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureNameFormatterFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Model
    {
        return new Model(
            $container->get('MvcTranslator'),
            $container->get('ViewRenderer'),
            $container->get(ItemNameFormatter::class)
        );
    }
}
