<?php

declare(strict_types=1);

namespace Application\Command;

use Application\Service\SpecificationsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SpecsRefreshUsersStatCommandFactory implements FactoryInterface
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
    ): SpecsRefreshUsersStatCommand {
        return new SpecsRefreshUsersStatCommand(
            'specs-refresh-users-stat',
            $container->get(SpecificationsService::class)
        );
    }
}
