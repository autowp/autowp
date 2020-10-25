<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PerspectiveFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Perspective
    {
        $tables = $container->get('TableManager');
        return new Perspective(
            $tables->get('perspectives'),
            $tables->get('perspectives_groups')
        );
    }
}
