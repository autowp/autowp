<?php

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;

return [
    'hydrators' => [
        'factories' => [
            Hydrator\Api\CommentHydrator::class => Hydrator\Api\Service\CommentHydratorFactory::class,
            Hydrator\Api\UserHydrator::class    => Hydrator\Api\Service\UserHydratorFactory::class
        ]
    ],
    'controllers' => [
        'factories' => [
            Controller\Api\CommentsController::class    => Controller\Api\Service\CommentsControllerFactory::class,
            Controller\Api\ContactsController::class    => InvokableFactory::class,
            Controller\Api\ItemsController::class       => Controller\Api\Service\ItemsControllerFactory::class,
            Controller\Api\PictureController::class     => Controller\Api\Service\PictureControllerFactory::class,
            Controller\Api\PictureItemController::class => Controller\Api\Service\PictureItemControllerFactory::class,
            Controller\Api\PictureModerVoteController::class => Controller\Api\Service\PictureModerVoteControllerFactory::class,
            Controller\Api\PictureVoteController::class => Controller\Api\Service\PictureVoteControllerFactory::class,
            Controller\Api\StatController::class        => InvokableFactory::class,
            Controller\Api\UsersController::class       => InvokableFactory::class,
        ]
    ],
    'router' => [
        'routes' => [
            'api' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/api',
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'comments' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/comments',
                            'defaults' => [
                                'controller' => Controller\Api\CommentsController::class,
                                'action'     => 'index'
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'subscribe' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/subscribe/:type_id/:item_id',
                                    'defaults' => [
                                        'action' => 'subscribe'
                                    ],
                                ],
                            ]
                        ]
                    ],
                    'contacts' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/contacts/:id',
                            'constraints' => [
                                'id' => '[0-9]+'
                            ],
                            'defaults' => [
                                'controller' => Controller\Api\ContactsController::class
                            ],
                        ],
                    ],
                    'picture' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/picture',
                            'defaults' => [
                                'controller' => Controller\Api\PictureController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'random_picture' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/random-picture',
                                    'defaults' => [
                                        'controller' => Controller\Api\PictureController::class,
                                        'action' => 'random-picture'
                                    ],
                                ]
                            ],
                            'new-picture' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/new-picture',
                                    'defaults' => [
                                        'controller' => Controller\Api\PictureController::class,
                                        'action' => 'new-picture'
                                    ],
                                ]
                            ],
                            'car-of-day-picture' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/car-of-day-picture',
                                    'defaults' => [
                                        'controller' => Controller\Api\PictureController::class,
                                        'action' => 'car-of-day-picture'
                                    ],
                                ]
                            ]
                        ]
                    ],
                    'picture-moder-vote' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/picture-moder-vote/:id',
                            'constraints' => [
                                'id' => '[0-9]+'
                            ],
                            'defaults' => [
                                'controller' => Controller\Api\PictureModerVoteController::class
                            ],
                        ],
                    ],
                    'picture-vote' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/picture-vote/:id',
                            'constraints' => [
                                'id' => '[0-9]+'
                            ],
                            'defaults' => [
                                'controller' => Controller\Api\PictureVoteController::class
                            ],
                        ],
                    ],
                    'picture-item' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/picture-item/:picture_id/:item_id',
                            'defaults' => [
                                'controller' => Controller\Api\PictureItemController::class,
                                'action'     => 'item'
                            ],
                        ],
                    ],
                    'users' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/users',
                            'defaults' => [
                                'controller' => Controller\Api\UsersController::class
                            ]
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'user' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:id'
                                ]
                            ]
                        ]
                    ],
                    'items' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/items',
                            'defaults' => [
                                'controller' => Controller\Api\ItemsController::class
                            ]
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'alpha' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/alpha',
                                    'defaults' => [
                                        'action' => 'alpha'
                                    ]
                                ]
                            ],
                            'alpha-items' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/alpha-items',
                                    'defaults' => [
                                        'action' => 'alpha-items'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'stat' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/stat',
                            'defaults' => [
                                'controller' => Controller\Api\StatController::class
                            ]
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'global-summary' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/global-summary',
                                    'defaults' => [
                                        'action' => 'global-summary'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]
];
