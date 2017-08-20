<?php

namespace Autowp\User;

class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke()
    {
        return [
            'controller_plugins' => $this->getControllerPluginConfig(),
            'dependencies'       => $this->getDependencyConfig(),
            'view_helpers'       => $this->getViewHelperConfig(),
        ];
    }

    /**
     * @return array
     */
    public function getControllerPluginConfig()
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
     *
     * @return array
     */
    public function getDependencyConfig()
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

    /**
     * @return array
     */
    public function getViewHelperConfig()
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
