<?php

namespace Application;

use Zend\Router\Http\Hostname;
use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
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
            /*'home' => [
             'type' => Literal::class,
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'index',
                    ],
                ],
            ],*/
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
            'ban' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/ban',
                    'defaults' => [
                        'controller' => Controller\BanController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'ban-user' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'  => '/ban-user/user_id/:user_id',
                            'defaults' => [
                                'action' => 'ban-user',
                            ]
                        ]
                    ],
                    'unban-user' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'  => '/unban-user/user_id/:user_id',
                            'defaults' => [
                                'action' => 'unban-user',
                            ]
                        ]
                    ],
                    'ban-ip' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'  => '/ban-ip/ip/:ip',
                            'defaults' => [
                                'action' => 'ban-ip',
                            ]
                        ]
                    ],
                    'unban-ip' => [
                        'type' => Segment::class,
                        'options' => [
                            'route'  => '/unban-ip/ip/:ip',
                            'defaults' => [
                                'action' => 'unban-ip',
                            ]
                        ]
                    ],
                ]
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
                        'controller' => Controller\CutawayController::class,
                        'action'     => 'index',
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
                    'success' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/success',
                            'defaults' => [
                                'action' => 'success',
                            ],
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
                    ]
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
            'forums' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/forums',
                    'defaults' => [
                        'controller' => Controller\ForumsController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'index' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/index[/:theme_id][/page:page]',
                        ]
                    ],
                    'topic' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/topic/:topic_id[/page:page]',
                            'defaults' => [
                                'action' => 'topic',
                            ],
                        ]
                    ],
                    'subscribe' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/subscribe/topic_id/:topic_id',
                            'defaults' => [
                                'action' => 'subscribe',
                            ],
                        ]
                    ],
                    'unsubscribe' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/unsubscribe/topic_id/:topic_id',
                            'defaults' => [
                                'action' => 'unsubscribe',
                            ],
                        ]
                    ],
                    'new' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/new/theme_id/:theme_id',
                            'defaults' => [
                                'action' => 'new',
                            ],
                        ]
                    ],
                    'topic-message' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/topic-message/message_id/:message_id',
                            'defaults' => [
                                'action' => 'topic-message',
                            ],
                        ]
                    ],
                    'open' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/open',
                            'defaults' => [
                                'action' => 'open',
                            ]
                        ]
                    ],
                    'close' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/close',
                            'defaults' => [
                                'action' => 'close',
                            ]
                        ]
                    ],
                    'delete' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/delete',
                            'defaults' => [
                                'action' => 'delete',
                            ]
                        ]
                    ],
                    'move' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/move/:topic_id',
                            'defaults' => [
                                'action' => 'move',
                            ]
                        ]
                    ],
                    'move-message' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/move-message/:id',
                            'defaults' => [
                                'action' => 'move-message',
                            ]
                        ]
                    ],
                    'subscribes' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/subscribes',
                            'defaults' => [
                                'action' => 'subscribes',
                            ]
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
            'log' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/log/index',
                    'defaults' => [
                        'controller' => Controller\LogController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'params' => [
                        'type' => Router\Http\WildcardSafe::class
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
                    'route' => '/new[/:date][/page:page]',
                    'defaults' => [
                        'controller' => Controller\NewController::class,
                        'action'     => 'index'
                    ]
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
                        'controller' => Controller\RulesController::class,
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
            'moder' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/moder',
                    'defaults' => [
                        'controller' => Controller\Moder\IndexController::class,
                        'action'     => 'index'
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'brands' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/brands[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\BrandsController::class,
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
                    'cars' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/cars[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\CarController::class,
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
                    'category' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/category[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\CategoryController::class,
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
                    'comments' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/comments[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\CommentsController::class,
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
                    'engines' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/engines[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\EnginesController::class,
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
                    'factories' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/factory[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\FactoryController::class,
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
                    'hotlink' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/hotlink',
                            'defaults' => [
                                'controller' => Controller\Moder\HotlinkController::class,
                                'action'     => 'index'
                            ]
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'clear-all' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/clear-all',
                                    'defaults' => [
                                        'action' => 'clear-all'
                                    ]
                                ]
                            ],
                            'clear-host' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/clear-host',
                                    'defaults' => [
                                        'action' => 'clear-host'
                                    ]
                                ]
                            ],
                            'whitelist-host' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/whitelist-host',
                                    'defaults' => [
                                        'action' => 'whitelist-host'
                                    ]
                                ]
                            ],
                            'whitelist-and-clear-host' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/whitelist-and-clear-host',
                                    'defaults' => [
                                        'action' => 'whitelist-and-clear-host'
                                    ]
                                ]
                            ],
                            'blacklist-host' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/blacklist-host',
                                    'defaults' => [
                                        'action' => 'blacklist-host'
                                    ]
                                ]
                            ],
                        ]
                    ],
                    'index' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/index[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\IndexController::class,
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
                    'museum' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/museum[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\MuseumController::class,
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
                    'pages' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/pages[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\PagesController::class,
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
                    'perspectives' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/perspectives',
                            'defaults' => [
                                'controller' => Controller\Moder\PerspectivesController::class,
                                'action'     => 'index'
                            ]
                        ]
                    ],
                    'pictures' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/pictures[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\PicturesController::class,
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
                    'rights' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/rights[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\RightsController::class,
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
                    'traffic' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/traffic',
                            'defaults' => [
                                'controller' => Controller\Moder\TrafficController::class,
                                'action'     => 'index'
                            ]
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'host-by-addr' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/host-by-addr',
                                    'defaults' => [
                                        'action' => 'host-by-addr'
                                    ]
                                ]
                            ],
                            'whitelist' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/whitelist',
                                    'defaults' => [
                                        'action' => 'whitelist'
                                    ]
                                ]
                            ],
                            'whitelist-remove' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/whitelist-remove',
                                    'defaults' => [
                                        'action' => 'whitelist-remove'
                                    ]
                                ]
                            ],
                            'whitelist-add' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/whitelist-add',
                                    'defaults' => [
                                        'action' => 'whitelist-add'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'twins' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/twins[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\TwinsController::class,
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
                    'users' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/users[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\UsersController::class,
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
                ]
            ],
            'api' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/api',
                ],
                'may_terminate' => false,
                'child_routes' => [
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
                    ]
                ]
            ]
        ]
    ]
];