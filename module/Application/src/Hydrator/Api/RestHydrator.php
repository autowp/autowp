<?php

namespace Application\Hydrator\Api;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class RestHydrator implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): AbstractRestHydrator {
        return new $requestedName($container);
    }
}
