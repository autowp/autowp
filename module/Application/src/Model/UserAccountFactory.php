<?php

declare(strict_types=1);

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserAccountFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UserAccount
    {
        $tables = $container->get('TableManager');
        return new UserAccount(
            $tables->get('user_account')
        );
    }
}
