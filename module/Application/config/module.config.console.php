<?php

namespace Application;

return [
    'console' => [
        'router' => [
            'routes' => [
                'build' => [
                    'options' => [
                        'route'    => 'build (js|css|all):action [--fast|-f]',
                        'defaults' => [
                            'controller' => Controller\Console\BuildController::class,
                        ]
                    ]
                ],
                'image-storage' => [
                    'options' => [
                        'route'    => 'image-storage (clear-empty-dirs):action <dirname>',
                        'defaults' => [
                            'controller' => Controller\Console\ImageStorageController::class,
                        ]
                    ]
                ],
                'users' => [
                    'options' => [
                        'route'    => 'users (refresh-vote-limits|restore-votes|clear-hashes|clear-renames):action',
                        'defaults' => [
                            'controller' => Controller\Console\UsersController::class,
                        ]
                    ]
                ],
                'maintenance' => [
                    'options' => [
                        'route'    => 'maintenance (dump|clear-sessions|rebuild-category-parent|rebuild-car-order-cache|comments-replies-count):action',
                        'defaults' => [
                            'controller' => Controller\Console\MaintenanceController::class,
                        ]
                    ]
                ],
                'message' => [
                    'options' => [
                        'route'    => 'message (clear-old-system-pm|clear-deleted-pm):action',
                        'defaults' => [
                            'controller' => Controller\Console\MessageController::class,
                        ]
                    ]
                ],
                'traffic' => [
                    'options' => [
                        'route'    => 'traffic (autoban|google|gc|clear-referer-monitoring):action',
                        'defaults' => [
                            'controller' => Controller\Console\TrafficController::class,
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
                        'route'    => 'pictures (clear-queue):action',
                        'defaults' => [
                            'controller' => Controller\Console\PicturesController::class,
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
                        'route'    => 'specs refresh-item-conflict-flags <type_id> <item_id>',
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
            ]
        ]
    ],
];