<?php

namespace Application;

return [
    'route_manager' => [
        'factories' => [
            Router\Http\Catalogue::class => Router\Http\CatalogueFactory::class
        ]
    ],
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
            'brands' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/brands',
                    'defaults' => [
                        'controller' => Controller\BrandsController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'newcars' => [
                        'type' => 'Segment',
                        'options' => [
                            'route'  => '/newcars/:brand_id',
                            'defaults' => [
                                'action' => 'newcars',
                            ]
                        ]
                    ]
                ]
            ],
            'cars' => [
                'type' => 'Segment',
                'options' => [
                    'route'    => '/cars/:action',
                    'defaults' => [
                        'controller' => Controller\CarsController::class,
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'params' => [
                        'type' => Router\Http\WildcardSafe::class
                    ]
                ]
            ],
            'catalogue' => [
                'type' => Router\Http\Catalogue::class,
                'options' => [
                    'defaults' => [
                        'controller' => Controller\CatalogueController::class,
                        'action'     => 'brand'
                    ]
                ]
            ],
            'categories' => [
                'type' => Router\Http\Category::class,
                'options' => [
                    'route'    => '/category',
                    'defaults' => [
                        'controller' => Controller\CategoryController::class,
                        'action'     => 'index',
                    ],
                ]
            ],
            'category-newcars' => [
                'type' => 'Segment',
                'options' => [
                    'route'  => '/category/newcars/:item_id',
                    'defaults' => [
                        'controller' => Controller\CategoryController::class,
                        'action'     => 'newcars',
                    ]
                ]
            ],
            'comments' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/comments',
                    'defaults' => [
                        'controller' => Controller\CommentsController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'add' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/add/type_id/:type_id/item_id/:item_id',
                            'defaults' => [
                                'action' => 'add',
                            ],
                        ]
                    ],
                    'delete' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/delete',
                            'defaults' => [
                                'action' => 'delete',
                            ]
                        ]
                    ],
                    'restore' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/restore',
                            'defaults' => [
                                'action' => 'restore',
                            ]
                        ]
                    ],
                    'vote' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/vote',
                            'defaults' => [
                                'action' => 'vote',
                            ]
                        ]
                    ],
                    'votes' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/votes',
                            'defaults' => [
                                'action' => 'votes',
                            ]
                        ]
                    ]
                ]
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
            'factories' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/factory',
                    'defaults' => [
                        'controller' => Controller\FactoriesController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'newcars' => [
                        'type' => 'Segment',
                        'options' => [
                            'route'  => '/newcars/:item_id',
                            'defaults' => [
                                'action' => 'newcars',
                            ]
                        ]
                    ],
                ]
            ],
            'inbox' => [ // TODO: delete
                'type'    => 'Segment',
                'options' => [
                    'route' => '/inbox[/:brand][/:date][/page:page][/]',
                    'defaults' => [
                        'controller' => Controller\InboxController::class,
                        'action'     => 'index'
                    ]
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
            'picture' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/picture',
                    'defaults' => [
                        'controller' => Controller\PictureController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'picture' => [
                        'type' => 'Segment',
                        'options' => [
                            'route'    => '/[:picture_id]',
                            'defaults' => [
                                'action' => 'index',
                            ],
                        ],
                    ],
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
            'twins' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/twins',
                    'defaults' => [
                        'controller' => Controller\TwinsController::class,
                        'action'     => 'index'
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'brand' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/:brand_catname[/page:page]',
                            'defaults' => [
                                'action' => 'brand',
                            ]
                        ]
                    ],
                    'group' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/group:id',
                            'defaults' => [
                                'action' => 'group',
                            ]
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'specifications' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/specifications',
                                    'defaults' => [
                                        'action' => 'specifications',
                                    ]
                                ],
                            ],
                            'pictures' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/pictures',
                                    'defaults' => [
                                        'action' => 'pictures',
                                    ]
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'picture' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/:picture_id',
                                            'defaults' => [
                                                'action' => 'picture',
                                            ]
                                        ],
                                        'may_terminate' => true,
                                        'child_routes' => [
                                            'gallery' => [
                                                'type' => 'Literal',
                                                'options' => [
                                                    'route' => '/gallery',
                                                    'defaults' => [
                                                        'action' => 'picture-gallery',
                                                    ]
                                                ],
                                            ],
                                        ]
                                    ],
                                    'page'    => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/page:page',
                                        ]
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'page'    => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/page:page',
                        ]
                    ],
                ]
            ],
            /*'widget' => [
             'type' => 'Literal',
                'options' => [
                    'route'    => '/widget',
                    'defaults' => [
                        'controller' => Controller\WidgetController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'picture-preview' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/picture-preview/picture_id/:picture_id',
                            'defaults' => [
                                'action' => 'picture-preview',
                            ]
                        ]
                    ]
                ]
            ],*/
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