<?php

declare(strict_types=1);

namespace Autowp\User\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserRenameFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UserRename
    {
        $tables = $container->get('TableManager');
        return new UserRename(
            $tables->get('user_renames')
        );
    }
}
