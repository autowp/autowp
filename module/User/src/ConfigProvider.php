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
                Controller\Plugin\User::class => Controller\Plugin\Service\UserFactory::class,
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
                Model\UserRename::class => Model\UserRenameFactory::class
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
                View\Helper\User::class => View\Helper\Service\UserFactory::class,
            ],
        ];
    }
}
