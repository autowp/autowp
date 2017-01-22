<?php

namespace Application;

use Zend\ServiceManager\Factory\InvokableFactory;

return [
    'controllers' => [
        'factories' => [
            Controller\Console\BuildController::class       => InvokableFactory::class,
            Controller\Console\CatalogueController::class   => Controller\Console\Service\CatalogueControllerFactory::class,
            Controller\Console\MaintenanceController::class => Controller\Console\Service\MaintenanceControllerFactory::class,
            Controller\Console\MidnightController::class    => Controller\Console\Service\MidnightControllerFactory::class,
            Controller\Console\PicturesController::class    => InvokableFactory::class,
            Controller\Console\RefererController::class     => InvokableFactory::class,
            Controller\Console\SpecsController::class       => Controller\Console\Service\SpecsControllerFactory::class,
            Controller\Console\TelegramController::class    => Controller\Console\Service\TelegramControllerFactory::class,
            Controller\Console\TwitterController::class     => Controller\Console\Service\TwitterControllerFactory::class,
            Controller\Console\UsersController::class       => Controller\Console\Service\UsersControllerFactory::class,
        ]
    ],
    'console' => [
        'router' => [
            'routes' => [
                'build' => [
                    'options' => [
                        'route'    => 'build (brands-sprite):action',
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
                'maintenance' => [
                    'options' => [
                        'route'    => 'maintenance (dump|clear-sessions|comments-replies-count):action',
                        'defaults' => [
                            'controller' => Controller\Console\MaintenanceController::class,
                        ]
                    ]
                ],
                'midnight' => [
                    'options' => [
                        'route'    => 'midnight (car-of-day):action',
                        'defaults' => [
                            'controller' => Controller\Console\MidnightController::class,
                        ]
                    ]
                ],
                'pictures' => [
                    'options' => [
                        'route'    => 'pictures (clear-queue|fill-point):action',
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
                        'route'    => 'specs (refresh-conflict-flags|refresh-users-stat|update-specs-volumes):action',
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
                ],
                'twitter' => [
                    'options' => [
                        'route'    => 'twitter (car-of-day):action',
                        'defaults' => [
                            'controller' => Controller\Console\TwitterController::class,
                        ]
                    ]
                ],
                'app-users' => [
                    'options' => [
                        'route'    => 'users (refresh-vote-limits|restore-votes|delete-unused):action',
                        'defaults' => [
                            'controller' => Controller\Console\UsersController::class,
                        ]
                    ]
                ],
            ]
        ]
    ],
];