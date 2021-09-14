<?php

declare(strict_types=1);

namespace Application\Command;

use Application\Service\SpecificationsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SpecsRefreshItemConflictFlagsCommandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): SpecsRefreshItemConflictFlagsCommand {
        return new SpecsRefreshItemConflictFlagsCommand(
            'specs-refresh-item-conflict-flags',
            $container->get(SpecificationsService::class)
        );
    }
}
