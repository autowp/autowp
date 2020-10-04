<?php

namespace Application;

return [
    'router' => [
        'routes' => [
            'yandex' => [
                'type'          => 'Literal',
                'options'       => [
                    'route'    => '/yandex',
                    'defaults' => [
                        'controller' => Controller\Frontend\YandexController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'informing' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/informing',
                            'defaults' => [
                                'action' => 'informing',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
