<?php

namespace Application;

use Zend\Router\Http\Hostname;
use Zend\Router\Http\Literal;
use Zend\Router\Http\Regex;
use Zend\Router\Http\Segment;

return [
    'route_manager' => [
        'factories' => [
            Router\Http\Catalogue::class => Router\Http\CatalogueFactory::class
        ]
    ],
    'router' => [
        'routes' => [
           'ng' => [
                'type' => Regex::class,
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
                        'hostname'   => 'i.wheelsage.org',
                        'controller' => Controller\PictureFileController::class,
                        'action'     => 'index'
                    ]
                ]
            ],
            'index' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'about' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/about',
                    'defaults' => [
                        'controller' => Controller\AboutController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'account' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/account',
                    'defaults' => [
                        'controller' => Controller\AccountController::class,
                        'action'     => 'profile',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'access' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/access',
                            'defaults' => [
                                'action' => 'access',
                            ],
                        ],
                    ],
                    'accounts' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/accounts',
                            'defaults' => [
                                'action' => 'accounts',
                            ],
                        ],
                    ],
                    'clear-sent-messages' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/clear-sent-messages',
                            'defaults' => [
                                'action' => 'clear-sent-messages',
                            ],
                        ],
                    ],
                    'clear-system-messages' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/clear-system-messages',
                            'defaults' => [
                                'action' => 'clear-system-messages',
                            ],
                        ],
                    ],
                    'contacts' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/contacts',
                            'defaults' => [
                                'action' => 'contacts',
                            ],
                        ],
                    ],
                    'delete' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/delete',
                            'defaults' => [
                                'action' => 'delete',
                            ],
                        ],
                    ],
                    'delete-personal-message' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/delete-personal-message',
                            'defaults' => [
                                'action' => 'delete-personal-message',
                            ],
                        ],
                    ],
                    'send-personal-message' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/send-personal-message',
                            'defaults' => [
                                'action' => 'send-personal-message',
                            ],
                        ],
                    ],
                    'email' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/email',
                            'defaults' => [
                                'action' => 'email',
                            ],
                        ],
                    ],
                    'emailcheck' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'    => '/emailcheck/:email_check_code',
                            'defaults' => [
                                'action' => 'emailcheck',
                            ],
                        ],
                    ],
                    'not-taken-pictures' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/not-taken-pictures',
                            'defaults' => [
                                'action' => 'not-taken-pictures',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'page' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route'    => '/page:page'
                                ]
                            ]
                        ]
                    ],
                    'personal-messages' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/pm',
                            'defaults' => [
                                'action' => 'personal-messages-inbox',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'page' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route'    => '/page:page'
                                ]
                            ],
                            'sent' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/sent',
                                    'defaults' => [
                                        'action' => 'personal-messages-sent',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'page' => [
                                        'type' => Segment::class,
                                        'options' => [
                                            'route'    => '/page:page'
                                        ]
                                    ]
                                ]
                            ],
                            'system' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/system',
                                    'defaults' => [
                                        'action' => 'personal-messages-system',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'page' => [
                                        'type' => Segment::class,
                                        'options' => [
                                            'route'    => '/page:page'
                                        ]
                                    ]
                                ]
                            ],
                            'user' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route'    => '/user:user_id',
                                    'defaults' => [
                                        'action' => 'personal-messages-user',
                                    ]
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'page' => [
                                        'type' => Segment::class,
                                        'options' => [
                                            'route'    => '/page:page'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'pictures' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/pictures',
                            'defaults' => [
                                'action' => 'pictures',
                            ],
                        ],
                    ],
                    'profile' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'    => '/profile[/:form]',
                            'defaults' => [
                                'action' => 'profile',
                            ],
                        ],
                    ],
                    'remove-account' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'    => '/remove-account/:service',
                            'defaults' => [
                                'action' => 'remove-account',
                            ],
                        ],
                    ],
                    'specs-conflicts' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/specs-conflicts',
                            'defaults' => [
                                'action' => 'specs-conflicts',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'params' => [
                                'type' => Router\Http\WildcardSafe::class
                            ]
                        ]
                    ],
                ]
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
                'type' => Literal::class,
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
                        'type' => Segment::class,
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
                'type' => Segment::class,
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
                'type' => Segment::class,
                'options' => [
                    'route'  => '/category/newcars/:item_id',
                    'defaults' => [
                        'controller' => Controller\CategoryController::class,
                        'action'     => 'newcars',
                    ]
                ]
            ],
            'chart' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/chart',
                    'defaults' => [
                        'controller' => Controller\ChartController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'years' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/years',
                            'defaults' => [
                                'action' => 'years',
                            ],
                        ],
                    ],
                    'years-data' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/years-data',
                            'defaults' => [
                                'action' => 'years-data',
                            ],
                        ],
                    ]
                ]
            ],
            'comments' => [
                'type' => Literal::class,
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
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/add/type_id/:type_id/item_id/:item_id',
                            'defaults' => [
                                'action' => 'add',
                            ],
                        ]
                    ],
                    'delete' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/delete',
                            'defaults' => [
                                'action' => 'delete',
                            ]
                        ]
                    ],
                    'restore' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/restore',
                            'defaults' => [
                                'action' => 'restore',
                            ]
                        ]
                    ],
                    'vote' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/vote',
                            'defaults' => [
                                'action' => 'vote',
                            ]
                        ]
                    ],
                    'votes' => [
                        'type' => Literal::class,
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
                'type' => Segment::class,
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
                'type' => Segment::class,
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
                'type' => Segment::class,
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
                'type' => Literal::class,
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
                        'type'    => Literal::class,
                        'options' => [
                            'route' => '/log',
                            'defaults' => [
                                'action' => 'log',
                            ]
                        ]
                    ],
                    'success' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/success',
                            'defaults' => [
                                'action' => 'success',
                            ],
                        ]
                    ],
                    'vod' => [
                        'type' => Literal::class,
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
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/success',
                                    'defaults' => [
                                        'action' => 'vod-success',
                                    ],
                                ]
                            ],
                            'select-item' => [
                                'type' => Literal::class,
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
                                'type' => Literal::class,
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
                                'type' => Segment::class,
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
                'type' => Literal::class,
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
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/factory/id/:id',
                            'defaults' => [
                                'action' => 'factory',
                            ],
                        ]
                    ],
                    'factory-cars' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/factory-cars/id/:id',
                            'defaults' => [
                                'action' => 'factory-cars',
                            ],
                        ]
                    ],
                    'newcars' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'  => '/newcars/:item_id',
                            'defaults' => [
                                'action' => 'newcars',
                            ]
                        ]
                    ],
                ]
            ],
            'feedback' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/feedback',
                    'defaults' => [
                        'controller' => Controller\FeedbackController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'sent' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/sent',
                            'defaults' => [
                                'action' => 'sent',
                            ],
                        ]
                    ]
                ]
            ],
            'inbox' => [
                'type'    => Segment::class,
                'options' => [
                    'route' => '/inbox[/:brand][/:date][/page:page][/]',
                    'defaults' => [
                        'controller' => Controller\InboxController::class,
                        'action'     => 'index'
                    ]
                ]
            ],
            'info' => [
                'type'    => Literal::class,
                'options' => [
                    'route' => '/info',
                    'defaults' => [
                        'controller' => Controller\InfoController::class,
                    ]
                ],
                'child_routes'  => [
                    'spec' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/spec',
                            'defaults' => [
                                'action' => 'spec',
                            ],
                        ]
                    ],
                    'text' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/text/id/:id',
                            'defaults' => [
                                'action' => 'text',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'revision' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/revision/:revision',
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'login' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/login',
                    'defaults' => [
                        'controller' => Controller\LoginController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'start' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/start/:type',
                            'defaults' => [
                                'action' => 'start',
                            ],
                        ]
                    ],
                    'callback' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/callback',
                            'defaults' => [
                                'action' => 'callback',
                            ],
                        ]
                    ],
                    'logout' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/logout',
                            'defaults' => [
                                'action' => 'logout',
                            ],
                        ]
                    ]
                ]
            ],
            'map' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/map',
                    'defaults' => [
                        'controller' => Controller\MapController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'data' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/data',
                            'defaults' => [
                                'action' => 'data',
                            ],
                        ]
                    ],
                    'index2' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/index2',
                            'defaults' => [
                                'action' => 'index2',
                            ],
                        ]
                    ]
                ]
            ],
            'mosts' => [
                'type' => Segment::class,
                'options' => [
                    'route'    => '/mosts[/:most_catname][/:shape_catname][/:years_catname]',
                    'defaults' => [
                        'controller' => Controller\MostsController::class,
                        'action'     => 'index'
                    ]
                ]
            ],
            'museums' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/museums',
                    'defaults' => [
                        'controller' => Controller\MuseumsController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'museum' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/museum/id/:id',
                            'defaults' => [
                                'action' => 'museum',
                            ],
                        ]
                    ]
                ]
            ],
            'new' => [
                'type'    => Segment::class,
                'options' => [
                    'route' => '/new',
                    'defaults' => [
                        'controller' => Controller\NewController::class,
                        'action'     => 'index'
                    ]
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'date' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/:date',
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'page' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/page:page',
                                ],
                            ],
                            'item' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route'    => '/item/:item_id[/page:page]',
                                    'defaults' => [
                                        'action' => 'item',
                                    ],
                                ],
                            ],
                        ]
                    ],
                ]
            ],
            'picture' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/picture',
                    'defaults' => [
                        'controller' => Controller\PictureController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'picture' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'    => '/[:picture_id]',
                            'defaults' => [
                                'action' => 'index',
                            ],
                        ],
                    ],
                ]
            ],
            'pulse' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/pulse',
                    'defaults' => [
                        'controller' => Controller\PulseController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'registration' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/registration',
                    'defaults' => [
                        'controller' => Controller\RegistrationController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'ok' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/ok',
                            'defaults' => [
                                'action' => 'ok',
                            ]
                        ]
                    ]
                ]
            ],
            'restorepassword' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/restorepassword',
                    'defaults' => [
                        'controller' => Controller\RestorePasswordController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'new' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/new/:code',
                            'defaults' => [
                                'action' => 'new',
                            ]
                        ]
                    ],
                    'saved' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/saved',
                            'defaults' => [
                                'action' => 'saved',
                            ]
                        ]
                    ]
                ]
            ],
            'rules' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/rules',
                    'defaults' => [
                        'controller' => Controller\DocController::class,
                        'action'     => 'rules',
                    ],
                ],
            ],
            'telegram' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/telegram',
                    'defaults' => [
                        'controller' => Controller\TelegramController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'telegram-webhook' => [
                'type' => Segment::class,
                'options' => [
                    'route'    => '/telegram/webhook/token/:token',
                    'defaults' => [
                        'controller' => Controller\TelegramController::class,
                        'action'     => 'webhook',
                    ],
                ],
            ],
            'twins' => [
                'type' => Literal::class,
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
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/:brand_catname[/page:page]',
                            'defaults' => [
                                'action' => 'brand',
                            ]
                        ]
                    ],
                    'group' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/group:id',
                            'defaults' => [
                                'action' => 'group',
                            ]
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'specifications' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/specifications',
                                    'defaults' => [
                                        'action' => 'specifications',
                                    ]
                                ],
                            ],
                            'pictures' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/pictures',
                                    'defaults' => [
                                        'action' => 'pictures',
                                    ]
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'picture' => [
                                        'type' => Segment::class,
                                        'options' => [
                                            'route' => '/:picture_id',
                                            'defaults' => [
                                                'action' => 'picture',
                                            ]
                                        ],
                                        'may_terminate' => true,
                                        'child_routes' => [
                                            'gallery' => [
                                                'type' => Literal::class,
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
                                        'type' => Segment::class,
                                        'options' => [
                                            'route' => '/page:page',
                                        ]
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'page'    => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/page:page',
                        ]
                    ],
                ]
            ],
            'users' => [
                'type' => Literal::class,
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
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/:user_id',
                            'defaults' => [
                                'action' => 'user',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'pictures' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/pictures',
                                    'defaults' => [
                                        'action' => 'pictures',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'brand' => [
                                        'type' => Segment::class,
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
                                'type' => Literal::class,
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
                    'online' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/online',
                            'defaults' => [
                                'action' => 'online',
                            ],
                        ]
                    ],
                    'rating' => [
                        'type' => Literal::class,
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
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/pictures',
                                    'defaults' => [
                                        'action' => 'rating',
                                        'rating' =>  'pictures'
                                    ],
                                ],
                            ],
                            'likes' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/likes',
                                    'defaults' => [
                                        'action' => 'rating',
                                        'rating' =>  'likes'
                                    ],
                                ],
                            ],
                            'picture-likes' => [
                                'type' => Literal::class,
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
                'type' => Literal::class,
                'options' => [
                    'route'    => '/voting',
                    'defaults' => [
                        'controller' => Controller\VotingController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'voting' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'    => '/voting/id/:id[/filter/:filter]',
                            'defaults' => [
                                'action' => 'voting'
                            ],
                        ]
                    ],
                    'voting-variant-votes' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'    => '/voting-variant-votes/id/:id',
                            'defaults' => [
                                'action' => 'voting-variant-votes'
                            ],
                        ]
                    ],
                    'vote' => [
                        'type' => Segment::class,
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
                'type' => Segment::class,
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
             'type' => Literal::class,
                'options' => [
                    'route'    => '/widget',
                    'defaults' => [
                        'controller' => Controller\WidgetController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'picture-preview' => [
                        'type' => Segment::class,
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
                'type' => Literal::class,
                'options' => [
                    'route' => '/yandex',
                    'defaults' => [
                        'controller' => Controller\Frontend\YandexController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'informing' => [
                        'type'    => Literal::class,
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