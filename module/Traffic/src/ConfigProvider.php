<?php

namespace Autowp\Traffic;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'console'      => $this->getConsoleConfig(),
            'controllers'  => $this->getControllersConfig(),
            'dependencies' => $this->getDependencyConfig(),
            'router'       => $this->getRouterConfig(),
            'tables'       => $this->getTablesConfig()
        ];
    }

    public function getConsoleConfig(): array
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

    public function getControllersConfig(): array
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
     */
    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                TrafficControl::class => Service\TrafficControlFactory::class
            ]
        ];
    }

    public function getRouterConfig(): array
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

    public function getTablesConfig(): array
    {
        return [
            'banned_ip'      => [],
            'ip_monitoring4' => [],
            'ip_whitelist'   => [],
        ];
    }
}
