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
