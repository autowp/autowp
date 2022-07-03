<?php

declare(strict_types=1);

namespace Application\Service;

use Application\ItemNameFormatter as Model;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemNameFormatterFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Model
    {
        return new Model(
            $container->get('MvcTranslator'),
            $container->get('ViewRenderer')
        );
    }
}
