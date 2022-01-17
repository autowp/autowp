<?php

declare(strict_types=1);

namespace Autowp\User;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'controller_plugins' => $this->getControllerPluginConfig(),
            'dependencies'       => $this->getDependencyConfig(),
            'tables'             => $this->getTablesConfig(),
        ];
    }

    public function getControllerPluginConfig(): array
    {
        return [
            'aliases'   => [
                'user' => Controller\Plugin\User::class,
                'User' => Controller\Plugin\User::class,
            ],
            'factories' => [
                Controller\Plugin\User::class => Controller\Plugin\UserFactory::class,
            ],
        ];
    }

    /**
     * Return application-level dependency configuration.
     */
    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                Model\User::class       => Model\UserFactory::class,
                Model\UserRename::class => Model\UserRenameFactory::class,
                Service\OAuth::class    => Service\OAuthFactory::class,
            ],
        ];
    }

    public function getTablesConfig(): array
    {
        return [
            'users' => [], //TODO: rename to user
        ];
    }
}
