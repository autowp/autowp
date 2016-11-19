<?php

namespace Autowp\Traffic;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;

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
            'view_manager' => $this->getViewManagerConfig(),
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
                Controller\ConsoleController::class => Controller\Service\ConsoleControllerFactory::class,
                Controller\ModerController::class   => Controller\Service\ModerControllerFactory::class,
                Controller\BanController::class     => Controller\Service\BanControllerFactory::class,
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
                TrafficControl::class => InvokableFactory::class
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
                ],
                'moder' => [
                    'child_routes' => [
                        'traffic' => [
                            'type' => Literal::class,
                            'options' => [
                                'route' => '/traffic',
                                'defaults' => [
                                    'controller' => Controller\ModerController::class,
                                    'action'     => 'index'
                                ]
                            ],
                            'may_terminate' => true,
                            'child_routes'  => [
                                'host-by-addr' => [
                                    'type' => Literal::class,
                                    'options' => [
                                        'route' => '/host-by-addr',
                                        'defaults' => [
                                            'action' => 'host-by-addr'
                                        ]
                                    ]
                                ],
                                'whitelist' => [
                                    'type' => Literal::class,
                                    'options' => [
                                        'route' => '/whitelist',
                                        'defaults' => [
                                            'action' => 'whitelist'
                                        ]
                                    ]
                                ],
                                'whitelist-remove' => [
                                    'type' => Literal::class,
                                    'options' => [
                                        'route' => '/whitelist-remove',
                                        'defaults' => [
                                            'action' => 'whitelist-remove'
                                        ]
                                    ]
                                ],
                                'whitelist-add' => [
                                    'type' => Literal::class,
                                    'options' => [
                                        'route' => '/whitelist-add',
                                        'defaults' => [
                                            'action' => 'whitelist-add'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function getViewManagerConfig()
    {
        return [
            'template_path_stack' => [
                __DIR__ . '/../view',
            ],
        ];
    }
}
