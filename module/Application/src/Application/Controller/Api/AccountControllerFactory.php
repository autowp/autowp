<?php

namespace Application\Controller\Api;

use Application\Model\UserAccount;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AccountControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AccountController
    {
        $tables = $container->get('TableManager');
        return new AccountController(
            $container->get(UserAccount::class),
            $container->get('ExternalLoginServiceManager'),
            $tables->get('login_state')
        );
    }
}
