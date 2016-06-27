<?php
/**
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;

use Application\Router\Http\WildcardSafe;

use Autowp\Image;
use Autowp\TextStorage;

use Zend_Application_Resource_Db;
use Zend_Application_Resource_Cachemanager;
use Zend_Application_Resource_Session;
use Zend_Cache_Core;
use Zend_Cache_Manager;
use Zend_Db_Adapter_Abstract;
use Zend_View;

use Exception;

$imageDir = APPLICATION_PATH . "/../public_html/image/";

return [
    'modules' => [
        'Zend\I18n',
        'Zend\Mvc\I18n',
    ],
    'router' => [
        'routes' => [
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
                        'type' => WildcardSafe::class
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
                'type' => Literal::class,
                'options' => [
                    'route'    => '/category',
                    'defaults' => [
                        'controller' => Controller\CategoryController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'category' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/:category_catname',
                            'defaults' => [
                                'action' => 'category',
                            ],
                        ]
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
                        'type' => WildcardSafe::class
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
            'twins' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/twins',
                    'defaults' => [
                        'controller' => Controller\TwinsController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'group' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/group:id',
                            'defaults' => [
                                'action' => 'group',
                            ]
                        ]
                    ]
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
            'moder' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/moder',
                    'defaults' => [
                        'controller' => Controller\Moder\IndexController::class,
                        'action'     => 'index'
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'cars' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/cars',
                            'defaults' => [
                                'controller' => Controller\Moder\CarController::class,
                                'action'     => 'index'
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'action' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'params' => [
                                        'type' => WildcardSafe::class
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'comments' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/comments',
                            'defaults' => [
                                'controller' => Controller\Moder\CommentsController::class,
                                'action'     => 'index'
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'action' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'params' => [
                                        'type' => WildcardSafe::class
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'factories' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/factory',
                            'defaults' => [
                                'controller' => Controller\Moder\FactoryController::class,
                                'action'     => 'index'
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'factory' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/factory/factory_id/:factory_id',
                                    'defaults' => [
                                        'action' => 'factory'
                                    ],
                                ],
                            ]
                        ]
                    ],
                    'pages' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/pages',
                            'defaults' => [
                                'controller' => Controller\Moder\PagesController::class,
                                'action'     => 'index'
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'action' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                ],
                                'may_terminate' => true,
                                'child_routes'  => [
                                    'params' => [
                                        'type' => WildcardSafe::class
                                    ]
                                ]
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
            ],
            'picture' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/picture/:picture_id'
                ],
                'defaults' => [
                    'controller' => Controller\PictureController::class,
                    'action'     => 'index',
                ],
            ]
        ],
    ],
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
    'controllers' => [
        'factories' => [
            Controller\AboutController::class => function($sm) {
                $acl = $sm->get(Acl::class);
                return new Controller\AboutController($acl);
            },
            Controller\ArticlesController::class     => InvokableFactory::class,
            Controller\BrandsController::class => function($sm) {
                $cache = $sm->get('longCache');
                return new Controller\BrandsController($cache);
            },
            Controller\CommentsController::class     => InvokableFactory::class,
            Controller\CutawayController::class      => InvokableFactory::class,
            Controller\DonateController::class       => InvokableFactory::class,
            Controller\FactoriesController::class    => InvokableFactory::class,
            Controller\FeedbackController::class     => function($sm) {
                $form = $sm->get('FeedbackForm');
                $transport = $sm->get('MailTransport');
                $options = $sm->get('Config')['feedback'];
                return new Controller\FeedbackController($form, $transport, $options);
            },
            Controller\IndexController::class        => InvokableFactory::class,
            Controller\InboxController::class        => InvokableFactory::class,
            Controller\InfoController::class => function($sm) {
                $textStorage = $sm->get(TextStorage\Service::class);
                return new Controller\InfoController($textStorage);
            },
            Controller\LogController::class          => InvokableFactory::class,
            Controller\LoginController::class        => InvokableFactory::class,
            Controller\MapController::class          => InvokableFactory::class,
            Controller\MostsController::class => function($sm) {
                $textStorage = $sm->get(TextStorage\Service::class);
                return new Controller\MostsController($textStorage);
            },
            Controller\NewController::class          => InvokableFactory::class,
            Controller\MuseumsController::class      => InvokableFactory::class,
            Controller\PulseController::class        => InvokableFactory::class,
            Controller\RestorePasswordController::class => function($sm) {
                $service = $sm->get(Service\UsersService::class);
                $restoreForm = $sm->get('RestorePasswordForm');
                $newPasswordForm = $sm->get('NewPasswordForm');
                $transport = $sm->get('MailTransport');
                return new Controller\RestorePasswordController($service, $restoreForm, $newPasswordForm, $transport);
            },
            Controller\RulesController::class        => InvokableFactory::class,
            Controller\UsersController::class => function($sm) {
                $cache = $sm->get('longCache');
                return new Controller\UsersController($cache);
            },
            Controller\Api\ContactsController::class => InvokableFactory::class,
            Controller\Api\PictureController::class  => InvokableFactory::class,
            Controller\Api\UsersController::class => InvokableFactory::class,
            Controller\Console\BuildController::class => InvokableFactory::class,
            Controller\Console\ImageStorageController::class => InvokableFactory::class,
            Controller\Console\MaintenanceController::class => function($sm) {
                $db = $sm->get(Zend_Db_Adapter_Abstract::class);
                $sessionConfig = $sm->get('Config')['session'];
                return new Controller\Console\MaintenanceController($db, $sessionConfig);
            },
            Controller\Console\MessageController::class => InvokableFactory::class,
            Controller\Console\MidnightController::class => InvokableFactory::class,
            Controller\Console\PicturesController::class => InvokableFactory::class,
            Controller\Console\SpecsController::class => InvokableFactory::class,
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
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'pic'       => Controller\Plugin\Pic::class,
            'catalogue' => Controller\Plugin\Catalogue::class,
        ],
        'factories' => [
            'car' => function ($sm) {
                $textStorage = $sm->get(TextStorage\Service::class);
                return new Controller\Plugin\Car($textStorage);
            },
            'imageStorage' => function($sm) {
                $storage = $sm->get(Image\Storage::class);
                return new Controller\Plugin\ImageStorage($storage);
            },
            'oauth2' => Factory\OAuth2PluginFactory::class,
            'user' => function($sm) {
                $acl = $sm->get(Acl::class);
                return new Controller\Plugin\User($acl);
            },
            'language' => function($sm) {
                $language = $sm->get(Language::class);
                return new Controller\Plugin\Language($language);
            },
        ]
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => [
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'car'         => View\Helper\Car::class,
            'pageEnv'     => View\Helper\PageEnv::class,
            'page'        => View\Helper\Page::class,
            'htmlA'       => View\Helper\HtmlA::class,
            'htmlImg'     => View\Helper\HtmlImg::class,
            'sidebar'     => View\Helper\Sidebar::class,
            'pageTitle'   => View\Helper\PageTitle::class,
            'breadcrumbs' => View\Helper\Breadcrumbs::class,
            'humanTime'   => View\Helper\HumanTime::class,
            'markdown'    => View\Helper\Markdown::class,
            'pastTimeIndicator' => View\Helper\PastTimeIndicator::class,
            'inlinePicture' => View\Helper\InlinePicture::class,
            'pic'         => View\Helper\Pic::class,
            'img'         => View\Helper\Img::class,
            'pictures'    => View\Helper\Pictures::class,
            'moderMenu'   => View\Helper\ModerMenu::class,
        ],
        'factories' => [
            'mainMenu' => function($sm) {
                return new View\Helper\MainMenu($sm->get(MainMenu::class));
            },
            'language' => function($sm) {
                return new View\Helper\Language($sm->get(Language::class));
            },
            'user' => function($sm) {
                $acl = $sm->get(Acl::class);
                return new View\Helper\User($acl);
            },
            'fileSize' => function($sm) {
                return new View\Helper\FileSize($sm->get(Language::class));
            },
            'humanDate' => function($sm) {
                $language = $sm->get(Language::class);
                return new View\Helper\HumanDate($language->getLanguage());
            },
            'comments' => function($sm) {
                $view = $sm->get(Zend_View::class);
                return new View\Helper\Comments($view);
            },
            'userText' => function($sm) {
                $router = $sm->get('Router');
                return new View\Helper\UserText($router);
            },
            'acl' => function($sm) {
                $acl = $sm->get(Acl::class);
                return new View\Helper\Acl($acl);
            },
            'imageStorage' => function($sm) {
                $imageStorage = $sm->get(Image\Storage::class);
                return new View\Helper\ImageStorage($imageStorage);
            },
        ]
    ],
    'translator' => [
        'locale' => 'ru',
        'fallbackLocale' => 'en',
        'translation_file_patterns' => [
            [
                'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.php'
            ]
        ],
    ],
    'db' => [
        'adapter' => 'PDO_MYSQL',
        'params' => [
            'host'     => '',
            'username' => '',
            'password' => '',
            'dbname'   => '',
            'charset'  => 'utf8'
        ],
        'isDefaultTableAdapter' => true,
        'defaultMetadataCache'  => 'fast',
        'params.driver_options.1002' => "set time_zone = 'UTC'"
    ],
    'service_manager' => [
        'factories' => [
            Image\Storage::class => function($sm) {
                $config = $sm->get('Config')['imageStorage'];
                $storage = new Image\Storage($config);

                $request = $sm->get('Request');
                if ($request instanceof \Zend\Http\Request) {
                    if ($request->getServer('HTTPS')) {
                        $storage->setForceHttps(true);
                    }
                }

                return $storage;
            },
            Service\UsersService::class => function($sm) {
                $config = $sm->get('Config')['users'];

                return new Service\UsersService($config);
            },
            Zend_Db_Adapter_Abstract::class => function($sm) {
                $config = $sm->get('Config');
                $resource = new Zend_Application_Resource_Db($config['db']);
                return $resource->init();
            },
            'session' => function($sm) {
                $config = $sm->get('Config');
                $resource = new Zend_Application_Resource_Session($config['session']);
                return $resource->init();
            },
            Service\TelegramService::class => function($sm) {
                $config = $sm->get('Config');
                $router  = $sm->get('HttpRouter');
                return new Service\TelegramService($config['telegram'], $router);
            },
            'translator' => \Zend\Mvc\I18n\TranslatorFactory::class,
            Acl::class => function($sm) {

                $config = $sm->get('Config')['acl'];

                $cacheManager = $sm->get(Zend_Cache_Manager::class);

                $cacheCore = $cacheManager->getCache($config['cache']);

                if (!$cacheCore instanceof Zend_Cache_Core) {
                    throw new Exception('Cache not initialized');
                }

                $acl = $cacheCore->load('__ACL__');
                if (!$acl instanceof Acl) {
                    $acl = new Acl();
                    $cacheCore->save($acl, null, [], $config['cacheLifetime']);
                }

                return $acl;
            },
            Zend_Cache_Manager::class => function($sm) {
                $config = $sm->get('Config');
                $resource = new Zend_Application_Resource_Cachemanager($config['cachemanager']);
                return $resource->init();
            },
            MainMenu::class => function($sm) {

                $router = $sm->get('HttpRouter');
                $language = $sm->get(Language::class);
                $cache = $sm->get('longCache');
                $request = $sm->get('Request');
                $config = $sm->get('Config');

                return new MainMenu($request, $router, $language, $cache, $config['hosts']);
            },
            Language::class => function($sm) {

                $request = $sm->get('Request');

                return new Language($request);
            },
            TextStorage\Service::class => function($sm) {
                $options = $sm->get('Config')['textstorage'];
                $options['dbAdapter'] = $sm->get(Zend_Db_Adapter_Abstract::class);
                return new TextStorage\Service($options);
            },
            Zend_View::class => function($sm) {
                return new Zend_View([
                    'scriptPath' => APPLICATION_PATH . '/modules/default/views/scripts/'
                ]);
            },
            'MailTransport' => function($sm) {
                $config = $sm->get('Config');
                $transport = new \Zend\Mail\Transport\Smtp();
                $transport->setOptions(
                    new \Zend\Mail\Transport\SmtpOptions(
                        $config['mail']['transport']['options']
                    )
                );

                return $transport;
            }
        ],
        'aliases' => [
            'ZF\OAuth2\Provider\UserId' => Provider\UserId\OAuth2UserIdProvider::class
        ],
        'abstract_factories' => [
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            //'Zend\Form\FormAbstractServiceFactory',
        ],
        /*'services' => [),
        'factories' => [),
        'initializators' => [),
        'delegators' => [),
        'shared' => [)*/

    ],
    'imageStorage' => [
        'imageTableName' => 'image',
        'formatedImageTableName' => 'formated_image',
        'fileMode' => 0644,
        'dirMode' => 0755,

        'dirs' => [
            'format' => [
                'path' => $imageDir . "format",
                'url'  => 'http://i.wheelsage.org/image/format/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ]
            ],
            'museum' => [
                'path' => $imageDir . "museum",
                'url'  => 'http://i.wheelsage.org/image/museum/',
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2
                    ]
                ]
            ],
            'user' => [
                'path' => $imageDir . "user",
                'url'  => 'http://i.wheelsage.org/image/user/',
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2
                    ]
                ]
            ],
            'brand' => [
                'path' => $imageDir . "brand",
                'url'  => 'http://i.wheelsage.org/image/brand/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ]
            ],
            'picture' => [
                'path' => $imageDir . "pictures",
                'url'  => 'http://i.wheelsage.org/pictures/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ]
            ]
        ],

        'formatedImageDirName' => 'format',

        'formats' => [
            'format9'    => [
                'fitType'    => 0,
                'width'      => 160,
                'height'     => 120,
                'background' => '#fff',
                'strip'      => 1
            ],
            'icon' => [
                'fitType'    => 0,
                'width'      => 70,
                'height'     => 70,
                'background' => 'transparent',
                'strip'      => 1
            ],
            'logo' => [
                'fitType'    => 1,
                'width'      => 120,
                'height'     => 120,
                'background' => '#F5F5F5',
                'strip'      => 1
            ],
            'photo' => [
                'fitType'    => 2,
                'width'      => 555,
                'height'     => 400,
                'background' => 'transparent',
                'reduceOnly' => 1,
                'strip'      => 1
            ],
            'avatar' => [
                'fitType'    => 0,
                'width'      => 70,
                'height'     => 70,
                'background' => 'transparent',
                'strip'      => 1
            ],
            'brandicon' => [
                'fitType'    => 1,
                'width'      => 70,
                'height'     => 70,
                'background' => '#EDE9DE',
                'strip'      => 1
            ],
            'brandicon2' => [
                'fitType'    => 2,
                'width'      => 70,
                'height'     => 70,
                'background' => 'transparent',
                'strip'      => 1
            ],
            'picture-thumb' => [
                'fitType'          => 0,
                'width'            => 155,
                'height'           => 116,
                'strip'            => 1,
                'format'           => 'jpeg',
                'proportionalCrop' => 1,
                'background'       => '#fff'
            ],
            'picture-thumb-medium' => [
                'fitType'          => 0,
                'width'            => 350,
                'height'           => 270,
                'strip'            => 1,
                'format'           => 'jpeg',
                'proportionalCrop' => 1
            ],
            'picture-medium' => [
                'fitType' => 0,
                'width'   => 350,
                'strip'   => 1,
                'format'  => 'jpeg'
            ],
            'picture-gallery' => [
                'fitType'    => 2,
                'width'      => 1024,
                'height'     => 768,
                'reduceOnly' => 1,
                'strip'      => 1,
                'format'     => 'jpeg'
            ],
            'picture-gallery-full' => [
                'fitType'    => 2,
                'width'      => 1024,
                'height'     => 768,
                'reduceOnly' => 1,
                'ignoreCrop' => 1,
                'strip'      => 1,
                'format'     => 'jpeg'
            ]
        ]
    ],

    'cachemanager' => [
        'fast' => [
            'frontend' => [
                'name' => 'Core',
                'customFrontendNaming' => 0,
                'options' => [
                    'lifetime' => 180,
                    'automatic_serialization' => true
                ]
            ],
            'backend' => [
                'name' => 'Memcached',
                'options' => [
                    'servers' => [
                        'host' => 'localhost',
                        'port' => 11211
                    ]
                ]
            ]
        ],
        'long' => [
            'frontend' => [
                'name' => 'Core',
                'customFrontendNaming' => 0,
                'options' => [
                    'lifetime' => 600,
                    'automatic_serialization' => true
                ]
            ],
            'backend' => [
                'name' => 'Memcached',
                'options' => [
                    'servers' => [
                        'host' => 'localhost',
                        'port' => 11211
                    ]
                ]
            ]
        ],
        'locale' => [
            'frontend' => [
                'name' => 'Core',
                'customFrontendNaming' => 0,
                'options' => [
                    'lifetime' => 600,
                    'automatic_serialization' => true
                ]
            ],
            'backend' => [
                'name' => 'Memcached',
                'options' => [
                    'servers' => [
                        'host' => 'localhost',
                        'port' => 11211
                    ]
                ]
            ]
        ]
    ],

    'session' => [
        'use_only_cookies'    => true,
        'gc_maxlifetime'      => 1440,
        'remember_me_seconds' => 86400,
        'saveHandler' => [
            'class' => "Project_Session_SaveHandler_DbTable",
            'options' => [
                'name'           => "session",
                'primary'        => "id",
                'modifiedColumn' => "modified",
                'dataColumn'     => "data",
                'lifetimeColumn' => "lifetime",
                'userIdColumn'   => "user_id"
            ]
        ]
    ],

    'telegram' => [
        'accessToken' => '',
        'token'       => '',
        'webhook'     => ''
    ],

    'twitter' => [
        'username' => '',
        'oauthOptions' => [
            'consumerKey'    => '',
            'consumerSecret' => ''
        ],
        'token' => [
            'oauth_token'        => '',
            'oauth_token_secret' => ''
        ]
    ],

    'hosts' => [
        'ru' => [
            'hostname' => 'www.autowp.ru',
            'timezone' => 'Europe/Moscow',
            'name'     => 'Русский',
            'flag'     => 'flag-RU'
        ],
        'en' => [
            'hostname' => 'en.wheelsage.org',
            'timezone' => 'Europe/London',
            'name'     => 'English (beta)',
            'flag'     => 'flag-GB'
        ],
        'fr' => [
            'hostname' => 'fr.wheelsage.org',
            'timezone' => 'Europe/Paris',
            'name'     => 'Français (beta)',
            'flag'     => 'flag-FR'
        ]
    ],

    'acl' => [
        'cache'         => 'long',
        'cacheLifetime' => 3600
    ],

    'textstorage' => [
        'textTableName'     => 'textstorage_text',
        'revisionTableName' => 'textstorage_revision'
    ],

    'caches' => [
        'fastCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'lifetime' => 180,
                'options'  => [
                    'servers'   => [
                        ['localhost', 11211]
                    ],
                    'namespace'  => 'FAST',
                    'liboptions' => [
                        'COMPRESSION'     => false,
                        'binary_protocol' => true,
                        'no_block'        => true,
                        'connect_timeout' => 100
                    ]
                ]
            ],
            /*'plugins' => [
                'exception_handler' => [
                    'throw_exceptions' => false
                ],
            ],*/
        ],
        'longCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'lifetime' => 600,
                'options'  => [
                    'servers'   => [
                        ['localhost', 11211]
                    ],
                    'namespace'  => 'LONG',
                    'liboptions' => [
                        'COMPRESSION'     => false,
                        'binary_protocol' => true,
                        'no_block'        => true,
                        'connect_timeout' => 100
                    ]
                ]
            ],
            /*'plugins' => [
             'exception_handler' => [
                 'throw_exceptions' => false
             ],
            ],*/
        ],
        'localeCache' => [
            'adapter' => [
                'name'     =>'memcached',
                'lifetime' => 600,
                'options'  => [
                    'servers'   => [
                        ['localhost', 11211]
                    ],
                    'namespace'  => 'LOCALE',
                    'liboptions' => [
                        'COMPRESSION'     => false,
                        'binary_protocol' => true,
                        'no_block'        => true,
                        'connect_timeout' => 100
                    ]
                ]
            ],
            /*'plugins' => [
             'exception_handler' => [
                 'throw_exceptions' => false
             ],
            ],*/
        ],
    ],

    'forms' => [
        'FeedbackForm' => [
            //'hydrator' => 'ObjectProperty',
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'feedback/title',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'name',
                        'options' => [
                            'label'        => 'feedback/name',
                            'maxlength'    => 255,
                            'size'         => 80,
                            'autocomplete' => 'name',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'email',
                        'options' => [
                            'label'        => 'E-mail',
                            'maxlength'    => 255,
                            'size'         => 80,
                            'autocomplete' => 'email',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Textarea',
                        'name' => 'message',
                        'options' => [
                            'label' => 'feedback/message',
                            'cols'  => 80,
                            'rows'  => 8,
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Captcha',
                        'name' => 'captcha',
                        'options' => [
                            'label' => 'login/captcha',
                            'captcha' => [
                                'class'   => 'Image',
                                'font'    => APPLICATION_PATH . '/resources/fonts/arial.ttf',
                                'imgDir'  => APPLICATION_PATH . '/../public_html/img/captcha/',
                                'imgUrl'  => '/img/captcha/',
                                'wordLen' => 4,
                                'timeout' => 300,
                            ]
                        ],
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Submit',
                        'name' => 'submit',
                        'attributes' => [
                            'value' => 'Send',
                        ]
                    ],
                ],
            ],
            'input_filter' => [
                'name' => [
                    'required'   => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'email' => [
                    'required'   => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        ['name' => 'EmailAddress']
                    ]
                ],
                'message' => [
                    'required'   => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ]
            ],
        ],
        'RestorePasswordForm' => [
            //'hydrator' => 'ObjectProperty',
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'email',
                        'options' => [
                            'label'        => 'E-mail',
                            'maxlength'    => 255,
                            'size'         => 80,
                            'autocomplete' => 'email',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Submit',
                        'name' => 'submit',
                        'attributes' => [
                            'value' => 'Send',
                        ]
                    ],
                ],
            ],
            'input_filter' => [
                'email' => [
                    'required'   => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        ['name' => 'EmailAddress']
                    ]
                ],
            ],
        ],
        'NewPasswordForm' => [
            //'hydrator' => 'ObjectProperty',
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Password',
                        'name' => 'password',
                        'options' => [
                            'label'        => 'Пароль',
                            'size'         => 20,
                            'maxlength'    => 50,
                            'autocomplete' => 'email',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Password',
                        'name' => 'password_confirm',
                        'options' => [
                            'label'        => 'Пароль (еще раз)',
                            'size'         => 20,
                            'maxlength'    => 50,
                            'autocomplete' => 'email',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Submit',
                        'name' => 'submit',
                        'attributes' => [
                            'value' => 'Send',
                        ]
                    ],
                ],
            ],
            'input_filter' => [
                'password' => [
                    'required'   => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        [
                            'name' => 'StringLength',
                            'options' => [
                                'min' => 6,
                                'max' => 50
                            ]
                        ]
                    ]
                ],
                'password_confirm' => [
                    'required'   => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        [
                            'name' => 'StringLength',
                            'options' => [
                                'min' => 6,
                                'max' => 50
                            ]
                        ],
                        [
                            'name' => 'Identical',
                            'options' => [
                                'token' => 'password',
                            ],
                        ]
                    ]
                ],
            ],
        ],
    ],

    'feedback' => [
        'from'     => 'no-reply@autowp.ru',
        'fromname' => 'robot autowp.ru',
        'to'       => 'autowp@gmail.com',
        'subject'  => 'AutoWP Feedback'
    ]
];
