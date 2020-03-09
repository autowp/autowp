<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SpecControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): SpecController
    {
        $tables = $container->get('TableManager');
        return new SpecController(
            $tables->get('spec')
        );
    }
}
