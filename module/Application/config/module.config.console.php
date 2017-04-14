<?php

namespace Application;

use Zend\ServiceManager\Factory\InvokableFactory;

return [
    'controllers' => [
        'factories' => [
            Controller\Console\BuildController::class     => InvokableFactory::class,
            Controller\Console\CatalogueController::class => Controller\Console\Service\CatalogueControllerFactory::class,
            Controller\Console\PicturesController::class  => Controller\Console\Service\PicturesControllerFactory::class,
            Controller\Console\RefererController::class   => InvokableFactory::class,
            Controller\Console\SpecsController::class     => Controller\Console\Service\SpecsControllerFactory::class,
            Controller\Console\TelegramController::class  => Controller\Console\Service\TelegramControllerFactory::class
        ]
    ],
    'console' => [
        'router' => [
            'routes' => [
                'build' => [
                    'options' => [
                        'route'    => 'build (brands-sprite|translations):action',
                        'defaults' => [
                            'controller' => Controller\Console\BuildController::class,
                        ]
                    ]
                ],
                'catalogue' => [
                    'options' => [
                        'route'    => 'catalogue (refresh-brand-vehicle|rebuild-car-order-cache):action',
                        'defaults' => [
                            'controller' => Controller\Console\CatalogueController::class,
                        ]
                    ]
                ],
                'pictures' => [
                    'options' => [
                        'route'    => 'pictures (fill-point):action',
                        'defaults' => [
                            'controller' => Controller\Console\PicturesController::class,
                        ]
                    ]
                ],
                'referer' => [
                    'options' => [
                        'route'    => 'traffic (clear-referer-monitoring):action',
                        'defaults' => [
                            'controller' => Controller\Console\RefererController::class,
                        ]
                    ]
                ],
                'specs' => [
                    'options' => [
                        'route'    => 'specs (refresh-conflict-flags|refresh-users-stat):action',
                        'defaults' => [
                            'controller' => Controller\Console\SpecsController::class,
                        ]
                    ]
                ],
                'specs-refresh-item-conflict-flags' => [
                    'options' => [
                        'route'    => 'specs refresh-item-conflict-flags <item_id>',
                        'defaults' => [
                            'controller' => Controller\Console\SpecsController::class,
                            'action'     => 'refresh-item-conflict-flags'
                        ]
                    ]
                ],
                'specs-refresh-user-stat' => [
                    'options' => [
                        'route'    => 'specs refresh-user-stat <user_id>',
                        'defaults' => [
                            'controller' => Controller\Console\SpecsController::class,
                            'action'     => 'refresh-user-stat'
                        ]
                    ]
                ],
                'telegram' => [
                    'options' => [
                        'route'    => 'telegram (register|notify-inbox):action',
                        'defaults' => [
                            'controller' => Controller\Console\TelegramController::class,
                        ]
                    ]
                ],
                'telegram-notify-inbox' => [
                    'options' => [
                        'route'    => 'telegram notify-inbox <picture_id>',
                        'defaults' => [
                            'controller' => Controller\Console\TelegramController::class,
                            'action'     => 'notify-inbox'
                        ]
                    ]
                ]
            ]
        ]
    ],
];