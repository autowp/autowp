<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Model\UserAccount;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AccountControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): AccountController
    {
        return new AccountController(
            $container->get(UserAccount::class)
        );
    }
}
