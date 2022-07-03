<?php

declare(strict_types=1);

namespace Application\Service;

use Application\DuplicateFinder;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DuplicateFinderFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): DuplicateFinder
    {
        $tables = $container->get('TableManager');
        return new DuplicateFinder(
            $container->get('RabbitMQ'),
            $tables->get('df_distance')
        );
    }
}
