<?php

namespace Application\Model;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BrandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Brand
    {
        return new Brand(
            $container->get(Item::class)
        );
    }
}
