<?php

namespace Autowp\User;

use Zend\ServiceManager\Factory\InvokableFactory;

class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke()
    {
        return [
            'console'            => $this->getConsoleConfig(),
            'controller_plugins' => $this->getControllerPluginConfig(),
            'controllers'        => $this->getControllersConfig(),
            'view_helpers'       => $this->getViewHelperConfig(),
        ];
    }

    /**
     * @return array
     */
    public function getConsoleConfig()
    {
        return [
            'router' => [
                'routes' => [
                    'users' => [
                        'options' => [
                            'route'    => 'users (clear-password-remind|clear-remember|clear-renames):action',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class,
                            ]
                        ]
                    ],
                ]
            ]
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
    public function getControllersConfig()
    {
        return [
            'factories' => [
                Controller\ConsoleController::class => InvokableFactory::class
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
