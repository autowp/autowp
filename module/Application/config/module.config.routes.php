<?php

namespace Application;

return [
    'router' => [
        'routes' => [
            'ng' => [
                'type' => 'Regex',
                'options' => [
                    'regex'    => '/ng/(?<path>[/a-zA-Z0-9_-]+)?',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'ng',
                    ],
                    'spec' => '/ng/%path%',
                ]
            ],
            'picture-file' => [
                'type' => Router\Http\PictureFile::class,
                'options' => [
                    'defaults' => [
                        'hostname'   => getenv('AUTOWP_PICTURES_HOST'),
                        'controller' => Controller\PictureFileController::class,
                        'action'     => 'index'
                    ]
                ]
            ],
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
            'donate-success' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/donate/success',
                    'defaults' => [
                        'controller' => Controller\DonateController::class,
                        'action'     => 'success',
                    ],
                ]
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
            'telegram-webhook' => [
                'type' => 'Segment',
                'options' => [
                    'route'    => '/telegram/webhook/token/:token',
                    'defaults' => [
                        'controller' => Controller\TelegramController::class,
                        'action'     => 'webhook',
                    ],
                ],
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
