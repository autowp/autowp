<?php

namespace Autowp\Traffic;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;

class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke()
    {
        return [
            'console'      => $this->getConsoleConfig(),
            'controllers'  => $this->getControllersConfig(),
            'dependencies' => $this->getDependencyConfig(),
            'router'       => $this->getRouterConfig(),
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
                    'traffic' => [
                        'options' => [
                            'route'    => 'traffic (autoban|google|gc):action',
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
                Controller\ConsoleController::class => Controller\ConsoleControllerFactory::class,
                Controller\BanController::class     => Controller\BanControllerFactory::class,
            ]
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
                TrafficControl::class => Service\TrafficControlFactory::class
            ]
        ];
    }

    /**
     * @return array
     */
    public function getRouterConfig()
    {
        return [
            'routes' => [
                'ban' => [
                    'type' => Literal::class,
                    'options' => [
                        'route'    => '/ban',
                        'defaults' => [
                            'controller' => Controller\BanController::class,
                            'action'     => 'index',
                        ],
                    ],
                    'may_terminate' => false,
                    'child_routes'  => [
                        'ban-user' => [
                            'type' => Segment::class,
                            'options' => [
                                'route'  => '/ban-user/user_id/:user_id',
                                'defaults' => [
                                    'action' => 'ban-user',
                                ]
                            ]
                        ],
                        'unban-user' => [
                            'type' => Segment::class,
                            'options' => [
                                'route'  => '/unban-user/user_id/:user_id',
                                'defaults' => [
                                    'action' => 'unban-user',
                                ]
                            ]
                        ],
                        'ban-ip' => [
                            'type' => Segment::class,
                            'options' => [
                                'route'  => '/ban-ip/ip/:ip',
                                'defaults' => [
                                    'action' => 'ban-ip',
                                ]
                            ]
                        ],
                        'unban-ip' => [
                            'type' => Segment::class,
                            'options' => [
                                'route'  => '/unban-ip/ip/:ip',
                                'defaults' => [
                                    'action' => 'unban-ip',
                                ]
                            ]
                        ],
                    ]
                ]
            ]
        ];
    }
}
