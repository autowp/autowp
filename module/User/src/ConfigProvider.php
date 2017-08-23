<?php

namespace Autowp\User;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'controller_plugins' => $this->getControllerPluginConfig(),
            'dependencies'       => $this->getDependencyConfig(),
            'tables'             => $this->getTablesConfig(),
            'view_helpers'       => $this->getViewHelperConfig(),
        ];
    }

    public function getControllerPluginConfig(): array
    {
        return [
            'aliases' => [
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
                Model\User::class               => Model\UserFactory::class,
                Model\UserPasswordRemind::class => Model\UserPasswordRemindFactory::class,
                Model\UserRename::class         => Model\UserRenameFactory::class,
                Model\UserRemember::class       => Model\UserRememberFactory::class
            ]
        ];
    }

    public function getTablesConfig(): array
    {
        return [
            'users' => [], //TODO: rename to user
        ];
    }

    public function getViewHelperConfig(): array
    {
        return [
            'aliases' => [
                'user' => View\Helper\User::class,
                'User' => View\Helper\User::class,
            ],
            'factories' => [
                View\Helper\User::class => View\Helper\UserFactory::class,
            ],
        ];
    }
}
