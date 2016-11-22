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
            'console'     => $this->getConsoleConfig(),
            'controllers' => $this->getControllersConfig(),
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
    public function getControllersConfig()
    {
        return [
            'factories' => [
                Controller\ConsoleController::class => InvokableFactory::class
            ]
        ];
    }
}
