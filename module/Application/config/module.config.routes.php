<?php

namespace Application;

return [
    'router' => [
        'routes' => [
            'index' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'login' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/login',
                    'defaults' => [
                        'controller' => Controller\Api\LoginController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'callback' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/callback',
                            'defaults' => [
                                'action' => 'callback',
                            ],
                        ]
                    ]
                ]
            ],
            'yandex' => [
                'type' => 'Literal',
                'options' => [
                    'route' => '/yandex',
                    'defaults' => [
                        'controller' => Controller\Frontend\YandexController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'informing' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route' => '/informing',
                            'defaults' => [
                                'action' => 'informing'
                            ]
                        ]
                    ]
                ]
            ],
        ]
    ]
];
