<?php

namespace Application;

use Zend\ServiceManager\Factory\InvokableFactory;

use Zend_Db_Adapter_Abstract;

return [
    'controllers' => [
        'factories' => [
            Controller\Console\ImageStorageController::class => InvokableFactory::class,
            Controller\Console\MaintenanceController::class => function($sm) {
                $db = $sm->get(Zend_Db_Adapter_Abstract::class);
                $sessionConfig = $sm->get('Config')['session'];
                return new Controller\Console\MaintenanceController($db, $sessionConfig);
            },
            Controller\Console\MessageController::class  => InvokableFactory::class,
            Controller\Console\MidnightController::class => InvokableFactory::class,
            Controller\Console\PicturesController::class => InvokableFactory::class,
            Controller\Console\SpecsController::class    => InvokableFactory::class,
            Controller\Console\TelegramController::class => function($sm) {
                $service = $sm->get(Service\TelegramService::class);
                return new Controller\Console\TelegramController($service);
            },
            Controller\Console\TrafficController::class => InvokableFactory::class,
            Controller\Console\TwitterController::class => function($sm) {
                $twitterConfig = $sm->get('Config')['twitter'];
                return new Controller\Console\TwitterController($twitterConfig);
            },
            Controller\Console\UsersController::class => function($sm) {
                $service = $sm->get(Service\UsersService::class);
                return new Controller\Console\UsersController($service);
            },
        ]
    ],
    'console' => [
        'router' => [
            'routes' => [
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