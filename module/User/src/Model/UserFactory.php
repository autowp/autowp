<?php

declare(strict_types=1);

namespace Autowp\User\Model;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function defined;

class UserFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): User
    {
        $tables = $container->get('TableManager');
        $config = $container->get('Config');
        return new User(
            $tables->get('users'),
            defined('PHPUNIT_COMPOSER_INSTALL') ? 0 : (int) $config['message_interval']
        );
    }
}
