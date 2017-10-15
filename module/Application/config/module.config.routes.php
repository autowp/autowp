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
            'about' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/about',
                    'defaults' => [
                        'controller' => Controller\AboutController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'articles' => [
                'type' => \Application\Router\Http\Articles::class,
                'options' => [
                    'route'    => '/articles',
                    'defaults' => [
                        'controller' => Controller\ArticlesController::class,
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
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
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
                'type' => \Application\Router\Http\Catalogue::class,
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
            'cutaway' => [
                'type' => 'Segment',
                'options' => [
                    'route'    => '/cutaway[/page:page]',
                    'defaults' => [
                        'controller'  => Controller\PerspectiveController::class,
                        'action'      => 'index',
                        'perspective' => 9,
                        'page_id'     => 109
                    ],
                ]
            ],
            'mascots' => [
                'type' => 'Segment',
                'options' => [
                    'route'    => '/mascots[/page:page]',
                    'defaults' => [
                        'controller'  => Controller\PerspectiveController::class,
                        'action'      => 'index',
                        'perspective' => 23,
                        'page_id'     => 201
                    ],
                ]
            ],
            'top-view' => [
                'type' => 'Segment',
                'options' => [
                    'route'    => '/top-view[/page:page]',
                    'defaults' => [
                        'controller'  => Controller\PerspectiveController::class,
                        'action'      => 'index',
                        'perspective' => 18,
                        'page_id'     => 201
                    ],
                ]
            ],
            'donate' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/donate',
                    'defaults' => [
                        'controller' => Controller\DonateController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'log' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route' => '/log',
                            'defaults' => [
                                'action' => 'log',
                            ]
                        ]
                    ],
                    'success' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/success',
                            'defaults' => [
                                'action' => 'success',
                            ],
                        ]
                    ],
                    'vod' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/vod',
                            'defaults' => [
                                'action' => 'vod',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'params' => [
                                'type' => Router\Http\WildcardSafe::class
                            ],
                            'success' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/success',
                                    'defaults' => [
                                        'action' => 'vod-success',
                                    ],
                                ]
                            ],
                            'select-item' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/vod-select-item',
                                    'defaults' => [
                                        'action' => 'vod-select-item',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'params' => [
                                        'type' => Router\Http\WildcardSafe::class
                                    ],
                                ]
                            ],
                            'vehicle-childs' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/vehicle-childs',
                                    'defaults' => [
                                        'action' => 'vehicle-childs',
                                    ],
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'params' => [
                                        'type' => Router\Http\WildcardSafe::class
                                    ],
                                ]
                            ],
                            'concepts' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/concepts/:brand_id',
                                    'defaults' => [
                                        'action' => 'concepts',
                                    ],
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'params' => [
                                        'type' => Router\Http\WildcardSafe::class
                                    ],
                                ]
                            ]
                        ]
                    ]
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
                    'factory' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/factory/id/:id',
                            'defaults' => [
                                'action' => 'factory',
                            ],
                        ]
                    ],
                    'factory-cars' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/factory-cars/id/:id',
                            'defaults' => [
                                'action' => 'factory-cars',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'page' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/page:page',
                                ],
                            ]
                        ]
                    ],
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
            'info' => [
                'type'    => 'Literal',
                'options' => [
                    'route' => '/info',
                    'defaults' => [
                        'controller' => Controller\InfoController::class,
                    ]
                ],
                'child_routes'  => [
                    'spec' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/spec',
                            'defaults' => [
                                'action' => 'spec',
                            ],
                        ]
                    ],
                    'text' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/text/id/:id',
                            'defaults' => [
                                'action' => 'text',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'revision' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/revision/:revision',
                                ]
                            ]
                        ]
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
            'mosts' => [
                'type' => 'Segment',
                'options' => [
                    'route'    => '/mosts[/:most_catname][/:shape_catname][/:years_catname]',
                    'defaults' => [
                        'controller' => Controller\MostsController::class,
                        'action'     => 'index'
                    ]
                ]
            ],
            'persons' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/persons',
                    'defaults' => [
                        'controller' => Controller\Frontend\PersonsController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'person' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/:id',
                            'defaults' => [
                                'action' => 'person',
                            ]
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'page'    => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/page:page',
                                ]
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
            'rules' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/rules',
                    'defaults' => [
                        'controller' => Controller\DocController::class,
                        'action'     => 'rules',
                    ],
                ],
            ],
            'telegram' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/telegram',
                    'defaults' => [
                        'controller' => Controller\TelegramController::class,
                        'action'     => 'index',
                    ],
                ],
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
            'users' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/users',
                    'defaults' => [
                        'controller' => Controller\UsersController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'user' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/:user_id',
                            'defaults' => [
                                'action' => 'user',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'pictures' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/pictures',
                                    'defaults' => [
                                        'action' => 'pictures',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'brand' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/:brand_catname[/page:page]',
                                            'defaults' => [
                                                'action' => 'brandpictures',
                                            ],
                                        ],
                                    ]
                                ]
                            ],
                            'comments' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/comments',
                                    'defaults' => [
                                        'action' => 'comments',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'params' => [
                                        'type' => Router\Http\WildcardSafe::class
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'rating' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/rating',
                            'defaults' => [
                                'action' => 'rating',
                                'rating' =>  'specs'
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'pictures' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/pictures',
                                    'defaults' => [
                                        'action' => 'rating',
                                        'rating' =>  'pictures'
                                    ],
                                ],
                            ],
                            'likes' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/likes',
                                    'defaults' => [
                                        'action' => 'rating',
                                        'rating' =>  'likes'
                                    ],
                                ],
                            ],
                            'picture-likes' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/picture-likes',
                                    'defaults' => [
                                        'action' => 'rating',
                                        'rating' =>  'picture-likes'
                                    ],
                                ],
                            ]
                        ]
                    ]
                ]
            ],
            'votings' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/voting',
                    'defaults' => [
                        'controller' => Controller\VotingController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'voting' => [
                        'type' => 'Segment',
                        'options' => [
                            'route'    => '/voting/id/:id[/filter/:filter]',
                            'defaults' => [
                                'action' => 'voting'
                            ],
                        ]
                    ],
                    'voting-variant-votes' => [
                        'type' => 'Segment',
                        'options' => [
                            'route'    => '/voting-variant-votes/id/:id',
                            'defaults' => [
                                'action' => 'voting-variant-votes'
                            ],
                        ]
                    ],
                    'vote' => [
                        'type' => 'Segment',
                        'options' => [
                            'route'    => '/vote/id/:id',
                            'defaults' => [
                                'action' => 'vote'
                            ],
                        ]
                    ]
                ]
            ],
            'upload' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/upload[/:action]',
                    'defaults' => [
                        'controller' => Controller\UploadController::class,
                        'action'     => 'index'
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'params' => [
                        'type' => Router\Http\WildcardSafe::class
                    ]
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