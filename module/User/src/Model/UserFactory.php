<?php

declare(strict_types=1);

namespace Autowp\User\Model;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): User
    {
        $tables = $container->get('TableManager');
        return new User($tables->get('users'));
    }
}
