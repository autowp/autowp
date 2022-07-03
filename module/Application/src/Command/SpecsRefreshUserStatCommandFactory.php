<?php

declare(strict_types=1);

namespace Application\Command;

use Application\Service\SpecificationsService;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SpecsRefreshUserStatCommandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): SpecsRefreshUserStatCommand {
        return new SpecsRefreshUserStatCommand(
            'specs-refresh-user-stat',
            $container->get(SpecificationsService::class)
        );
    }
}
