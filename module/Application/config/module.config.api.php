<?php

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Method;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;

use Autowp\User\Model\User;

return [
    'hydrators' => [
        'factories' => [
            Hydrator\Api\CommentHydrator::class          => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ForumThemeHydrator::class       => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ForumTopicHydrator::class       => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\IpHydrator::class               => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemHydrator::class             => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemHydrator::class             => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemLanguageHydrator::class     => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemLinkHydrator::class         => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemParentHydrator::class       => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemParentLanguageHydrator::class => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\LogHydrator::class              => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\MessageHydrator::class          => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\PerspectiveHydrator::class      => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\PerspectiveGroupHydrator::class => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\PerspectivePageHydrator::class  => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\PictureHydrator::class          => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\PictureItemHydrator::class      => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\TrafficHydrator::class          => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\SimilarHydrator::class          => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\UserHydrator::class             => Hydrator\Api\RestHydratorFactory::class
        ]
    ],
    'controllers' => [
        'factories' => [
            Controller\Api\AclController::class             => Controller\Api\Service\AclControllerFactory::class,
            Controller\Api\ChartController::class           => Controller\Api\ChartControllerFactory::class,
            Controller\Api\CommentController::class         => Controller\Api\CommentControllerFactory::class,
            Controller\Api\ContactsController::class        => Controller\Api\ContactsControllerFactory::class,
            Controller\Api\ContentLanguageController::class => Controller\Api\ContentLanguageControllerFactory::class,
            Controller\Api\FeedbackController::class        => Controller\Api\FeedbackControllerFactory::class,
            Controller\Api\ForumController::class           => Controller\Api\ForumControllerFactory::class,
            Controller\Api\HotlinksController::class        => InvokableFactory::class,
            Controller\Api\IpController::class              => Controller\Api\Service\IpControllerFactory::class,
            Controller\Api\ItemController::class            => Controller\Api\Service\ItemControllerFactory::class,
            Controller\Api\ItemLanguageController::class    => Controller\Api\ItemLanguageControllerFactory::class,
            Controller\Api\ItemLinkController::class        => Controller\Api\ItemLinkControllerFactory::class,
            Controller\Api\ItemParentController::class      => Controller\Api\ItemParentControllerFactory::class,
            Controller\Api\ItemParentLanguageController::class => Controller\Api\ItemParentLanguageControllerFactory::class,
            Controller\Api\ItemVehicleTypeController::class => Controller\Api\Service\ItemVehicleTypeControllerFactory::class,
            Controller\Api\LanguageController::class        => Controller\Api\LanguageControllerFactory::class,
            Controller\Api\LogController::class             => Controller\Api\Service\LogControllerFactory::class,
            Controller\Api\LoginController::class           => Controller\Api\LoginControllerFactory::class,
            Controller\Api\MapController::class             => Controller\Api\MapControllerFactory::class,
            Controller\Api\MessageController::class         => Controller\Api\MessageControllerFactory::class,
            Controller\Api\PageController::class            => Controller\Api\Service\PageControllerFactory::class,
            Controller\Api\PerspectiveController::class     => Controller\Api\Service\PerspectiveControllerFactory::class,
            Controller\Api\PerspectivePageController::class => Controller\Api\Service\PerspectivePageControllerFactory::class,
            Controller\Api\PictureController::class         => Controller\Api\PictureControllerFactory::class,
            Controller\Api\PictureItemController::class     => Controller\Api\PictureItemControllerFactory::class,
            Controller\Api\PictureModerVoteController::class => Controller\Api\PictureModerVoteControllerFactory::class,
            Controller\Api\PictureModerVoteTemplateController::class => Controller\Api\Service\PictureModerVoteTemplateControllerFactory::class,
            Controller\Api\PictureVoteController::class     => Controller\Api\Service\PictureVoteControllerFactory::class,
            Controller\Api\PulseController::class           => Controller\Api\PulseControllerFactory::class,
            Controller\Api\RecaptchaController::class       => Controller\Api\RecaptchaControllerFactory::class,
            Controller\Api\RestorePasswordController::class => Controller\Api\RestorePasswordControllerFactory::class,
            Controller\Api\SpecController::class            => Controller\Api\SpecControllerFactory::class,
            Controller\Api\StatController::class            => Controller\Api\StatControllerFactory::class,
            Controller\Api\TimezoneController::class        => InvokableFactory::class,
            Controller\Api\TrafficController::class         => Controller\Api\Service\TrafficControllerFactory::class,
            Controller\Api\UserController::class            => Controller\Api\UserControllerFactory::class,
            Controller\Api\VehicleTypesController::class    => Controller\Api\VehicleTypesControllerFactory::class
        ]
    ],

    'router' => [
        'routes' => [
            'api' => [
                'type' => 'Literal',
                'options' => [
                    'route' => '/api',
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'acl' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/acl',
                            'defaults' => [
                                'controller' => Controller\Api\AclController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'inherit-roles' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/inherit-roles',
                                    'defaults' => [
                                        'action' => 'inherit-roles'
                                    ],
                                ]
                            ],
                            'is-allowed' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/is-allowed',
                                    'defaults' => [
                                        'action' => 'is-allowed'
                                    ],
                                ]
                            ],
                            'roles' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/roles'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'roles'
                                            ]
                                        ]
                                    ],
                                    'post' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'roles-post'
                                            ]
                                        ]
                                    ],
                                    'role' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/:role',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'get' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'get',
                                                    'defaults' => [
                                                        'action' => 'role'
                                                    ]
                                                ]
                                            ],
                                            'parents' => [
                                                'type' => 'Literal',
                                                'options' => [
                                                    'route'    => '/parents',
                                                    'defaults' => [
                                                        'action' => 'role-parents'
                                                    ],
                                                ],
                                                'may_terminate' => false,
                                                'child_routes' => [
                                                    'get' => [
                                                        'type' => 'Method',
                                                        'options' => [
                                                            'verb' => 'get',
                                                            'defaults' => [
                                                                'action' => 'role-parents'
                                                            ]
                                                        ]
                                                    ],
                                                    'post' => [
                                                        'type' => 'Method',
                                                        'options' => [
                                                            'verb' => 'post',
                                                            'defaults' => [
                                                                'action' => 'role-parents-post'
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                            'resources' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/resources',
                                    'defaults' => [
                                        'action' => 'resources'
                                    ],
                                ]
                            ],
                            'rules' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/rules'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'rules'
                                            ]
                                        ]
                                    ],
                                    'post' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'rules-post'
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'chart' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/chart',
                            'defaults' => [
                                'controller' => Controller\Api\ChartController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'years' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/parameters',
                                    'defaults' => [
                                        'action' => 'parameters',
                                    ],
                                ],
                            ],
                            'years-data' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/data',
                                    'defaults' => [
                                        'action' => 'data',
                                    ],
                                ],
                            ]
                        ]
                    ],
                    'comment' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/comment',
                            'defaults' => [
                                'controller' => Controller\Api\CommentController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'subscribe' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/subscribe/:type_id/:item_id',
                                    'defaults' => [
                                        'action' => 'subscribe'
                                    ],
                                ],
                            ],
                            'get' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ],
                                ],
                            ],
                            'post' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ],
                                ],
                            ],
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id',
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'get'
                                            ],
                                        ],
                                    ],
                                    'put' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'put'
                                            ],
                                        ],
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'contacts' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/contacts',
                            'defaults' => [
                                'controller' => Controller\Api\ContactsController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'get' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id',
                                    'constraints' => [
                                        'id' => '[0-9]+'
                                    ],

                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'delete' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'delete',
                                            'defaults' => [
                                                'action' => 'delete'
                                            ]
                                        ]
                                    ],
                                    'put' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'put'
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'content-language' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/content-language',
                            'defaults' => [
                                'controller' => Controller\Api\ContentLanguageController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'index' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                        ]
                    ],
                    'feedback' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/feedback',
                            'defaults' => [
                                'controller' => Controller\Api\FeedbackController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'post' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ],
                                ],
                            ],
                        ]
                    ],
                    'forum' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/forum',
                            'defaults' => [
                                'controller' => Controller\Api\ForumController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'user-summary' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/user-summary',
                                    'defaults' => [
                                        'action' => 'user-summary'
                                    ],
                                ],
                            ],
                            'themes' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/themes'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'    => 'get',
                                            'defaults' => [
                                                'action' => 'get-themes'
                                            ],
                                        ],
                                    ],
                                    'item' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/:id'
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'get' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb'    => 'get',
                                                    'defaults' => [
                                                        'action' => 'get-theme'
                                                    ],
                                                ],
                                            ],
                                        ]
                                    ]
                                ]
                            ],
                            'topic' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/topic'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'post' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'    => 'post',
                                            'defaults' => [
                                                'action' => 'post-topic'
                                            ],
                                        ],
                                    ],
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'    => 'get',
                                            'defaults' => [
                                                'action' => 'get-topics'
                                            ],
                                        ],
                                    ],
                                    'item' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/:id'
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'get' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb'    => 'get',
                                                    'defaults' => [
                                                        'action' => 'get-topic'
                                                    ],
                                                ],
                                            ],
                                            'put' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb'    => 'put',
                                                    'defaults' => [
                                                        'action' => 'put-topic'
                                                    ],
                                                ],
                                            ],
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'hotlinks' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/hotlinks',
                            'defaults' => [
                                'controller' => Controller\Api\HotlinksController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'blacklist' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/blacklist'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'post' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'blacklist-post'
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                            'whitelist' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/whitelist'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'post' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'whitelist-post'
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                            'hosts' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/hosts'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'hosts'
                                            ]
                                        ]
                                    ],
                                    'delete' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'delete',
                                            'defaults' => [
                                                'action' => 'hosts-delete'
                                            ]
                                        ]
                                    ],
                                    'host' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/:host',
                                            'defaults' => [
                                                'action' => 'host'
                                            ]
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'delete',
                                                    'defaults' => [
                                                        'action' => 'host-delete'
                                                    ]
                                                ]
                                            ],
                                        ]
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'ip' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/ip/:ip',
                            'defaults' => [
                                'controller' => Controller\Api\IpController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'item' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'item'
                                    ]
                                ]
                            ],
                        ]
                    ],
                    'item' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/item',
                            'defaults' => [
                                'controller' => Controller\Api\ItemController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'post' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ]
                                ]
                            ],
                            'alpha' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/alpha',
                                    'defaults' => [
                                        'action' => 'alpha'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id',
                                    'constraints' => [
                                        'id' => '[0-9]+'
                                    ],
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'put' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'put'
                                            ]
                                        ]
                                    ],
                                    'logo' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route' => '/logo',
                                            'defaults' => [
                                                'action' => 'logo'
                                            ]
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'get' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'get',
                                                    'defaults' => [
                                                        'action' => 'get-logo'
                                                    ]
                                                ]
                                            ],
                                            'put' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'put',
                                                    'defaults' => [
                                                        'action' => 'put-logo'
                                                    ]
                                                ]
                                            ],
                                        ]
                                    ],
                                    'language' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route' => '/language',
                                            'defaults' => [
                                                'controller' => Controller\Api\ItemLanguageController::class
                                            ]
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'index' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'get',
                                                    'defaults' => [
                                                        'action' => 'index'
                                                    ]
                                                ]
                                            ],
                                            'item' => [
                                                'type' => 'Segment',
                                                'options' => [
                                                    'route' => '/:language'
                                                ],
                                                'may_terminate' => false,
                                                'child_routes' => [
                                                    'get' => [
                                                        'type' => 'Method',
                                                        'options' => [
                                                            'verb' => 'get',
                                                            'defaults' => [
                                                                'action' => 'get'
                                                            ]
                                                        ]
                                                    ],
                                                    'put' => [
                                                        'type' => 'Method',
                                                        'options' => [
                                                            'verb' => 'put',
                                                            'defaults' => [
                                                                'action' => 'put'
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                    'tree' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route' => '/tree',
                                            'defaults' => [
                                                'action' => 'tree'
                                            ]
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'get' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'get',
                                                    'defaults' => [
                                                        'action' => 'tree'
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ],
                    'item-link' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/item-link',
                            'defaults' => [
                                'controller' => Controller\Api\ItemLinkController::class
                            ]
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'index' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'post' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'get'
                                            ]
                                        ]
                                    ],
                                    'put' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'put'
                                            ]
                                        ]
                                    ],
                                    'delete' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'delete',
                                            'defaults' => [
                                                'action' => 'delete'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'item-parent' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/item-parent',
                            'defaults' => [
                                'controller' => Controller\Api\ItemParentController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:item_id/:parent_id',
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'put' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'put',
                                            'defaults' => [
                                                'action' => 'put'
                                            ]
                                        ]
                                    ],
                                    'delete' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'delete',
                                            'defaults' => [
                                                'action' => 'delete'
                                            ]
                                        ]
                                    ],
                                    'language' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route' => '/language',
                                            'defaults' => [
                                                'controller' => Controller\Api\ItemParentLanguageController::class
                                            ]
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'index' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'get',
                                                    'defaults' => [
                                                        'action' => 'index'
                                                    ]
                                                ]
                                            ],
                                            'item' => [
                                                'type' => 'Segment',
                                                'options' => [
                                                    'route' => '/:language'
                                                ],
                                                'may_terminate' => false,
                                                'child_routes' => [
                                                    'get' => [
                                                        'type' => 'Method',
                                                        'options' => [
                                                            'verb' => 'get',
                                                            'defaults' => [
                                                                'action' => 'get'
                                                            ]
                                                        ]
                                                    ],
                                                    'put' => [
                                                        'type' => 'Method',
                                                        'options' => [
                                                            'verb' => 'put',
                                                            'defaults' => [
                                                                'action' => 'put'
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                            'post' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb'     => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ]
                                ]
                            ],
                        ]
                    ],
                    'item-vehicle-type' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/item-vehicle-type',
                            'defaults' => [
                                'controller' => Controller\Api\ItemVehicleTypeController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'index' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:item_id/:vehicle_type_id',
                                    'defaults' => [
                                        'controller' => Controller\Api\ItemVehicleTypeController::class,
                                    ],
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'post' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'post',
                                            'defaults' => [
                                                'action' => 'create'
                                            ]
                                        ]
                                    ],
                                    'delete' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'delete',
                                            'defaults' => [
                                                'action' => 'delete'
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ],
                    'language' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/language',
                            'defaults' => [
                                'controller' => Controller\Api\LanguageController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'list'
                                    ]
                                ]
                            ],
                        ]
                    ],
                    'log' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/log',
                            'defaults' => [
                                'controller' => Controller\Api\LogController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'get' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
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
                        'child_routes' => [
                            'services' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/services'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'services'
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                            'login' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'login'
                                    ]
                                ]
                            ],
                            'logout' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'delete',
                                    'defaults' => [
                                        'action' => 'delete'
                                    ]
                                ]
                            ],
                            'start' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/start',
                                    'defaults' => [
                                        'action' => 'start',
                                    ],
                                ]
                            ],
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
                    'map' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/map',
                            'defaults' => [
                                'controller' => Controller\Api\MapController::class,
                                'action'     => 'index',
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'data' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/data',
                                    'defaults' => [
                                        'action' => 'data',
                                    ],
                                ]
                            ]
                        ]
                    ],
                    'message' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/message',
                            'defaults' => [
                                'controller' => Controller\Api\MessageController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'get' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'post' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ]
                                ]
                            ],
                            'delete' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'delete',
                                    'defaults' => [
                                        'action' => 'delete-list'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route'    => '/:id',
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    /*'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],*/
                                    'delete' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'delete',
                                            'defaults' => [
                                                'action' => 'delete'
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                            'summary' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/summary',
                                    'defaults' => [
                                        'action' => 'summary'
                                    ],
                                ],
                            ],
                            'new' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/new',
                                    'defaults' => [
                                        'action' => 'new'
                                    ],
                                ],
                            ],
                        ]
                    ],
                    'page' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/page',
                            'defaults' => [
                                'controller' => Controller\Api\PageController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'post' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'put' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'item-put'
                                            ]
                                        ]
                                    ],
                                    'delete' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'delete',
                                            'defaults' => [
                                                'action' => 'item-delete'
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                            [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/parents',
                                    'defaults' => [
                                        'action' => 'parents',
                                    ],
                                ],
                            ]
                        ]
                    ],
                    'picture' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/picture',
                            'defaults' => [
                                'controller' => Controller\Api\PictureController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'index' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'picture' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'accept-replace' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route'    => '/accept-replace',
                                            'defaults' => [
                                                'action' => 'accept-replace'
                                            ],
                                        ],
                                    ],
                                    'normalize' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route'    => '/normalize',
                                            'defaults' => [
                                                'action' => 'normalize'
                                            ],
                                        ],
                                    ],
                                    'flop' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route'    => '/flop',
                                            'defaults' => [
                                                'action' => 'flop'
                                            ],
                                        ],
                                    ],
                                    'repair' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route'    => '/repair',
                                            'defaults' => [
                                                'action' => 'repair'
                                            ],
                                        ],
                                    ],
                                    'correct-file-names' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route'    => '/correct-file-names',
                                            'defaults' => [
                                                'action' => 'correct-file-names'
                                            ],
                                        ],
                                    ],
                                    'similar' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/similar/:similar_picture_id',
                                            'constraints' => [
                                                'similar_picture_id' => '[0-9]+'
                                            ],
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'delete',
                                                    'defaults' => [
                                                        'action' => 'delete-similar'
                                                    ]
                                                ]
                                            ],
                                        ]
                                    ],
                                    'item' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'update' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'update'
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'random_picture' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/random-picture',
                                    'defaults' => [
                                        'action' => 'random-picture'
                                    ],
                                ]
                            ],
                            'new-picture' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/new-picture',
                                    'defaults' => [
                                        'action' => 'new-picture'
                                    ],
                                ]
                            ],
                            'car-of-day-picture' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/car-of-day-picture',
                                    'defaults' => [
                                        'action' => 'car-of-day-picture'
                                    ],
                                ]
                            ],
                            'user-summary' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/user-summary',
                                    'defaults' => [
                                        'action' => 'user-summary'
                                    ],
                                ],
                            ],
                        ]
                    ],
                    'picture-moder-vote' => [
                        'type' => 'Segment',
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
                        'type' => 'Segment',
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
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/picture-item',
                            'defaults' => [
                                'controller' => Controller\Api\PictureItemController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'get' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ] ,
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:picture_id/:item_id/:type'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'item' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'create' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'post',
                                            'defaults' => [
                                                'action' => 'create'
                                            ]
                                        ]
                                    ],
                                    'update' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'put',
                                            'defaults' => [
                                                'action' => 'update'
                                            ]
                                        ]
                                    ],
                                    'delete' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb'     => 'delete',
                                            'defaults' => [
                                                'action' => 'delete'
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ],
                    'pulse' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/pulse',
                            'defaults' => [
                                'controller' => Controller\Api\PulseController::class,
                                'action'     => 'index',
                            ],
                        ],
                    ],
                    'recaptcha' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/recaptcha',
                            'defaults' => [
                                'controller' => Controller\Api\RecaptchaController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'get' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'get'
                                    ],
                                ],
                            ],
                        ]
                    ],
                    'restore-password' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/restore-password',
                            'defaults' => [
                                'controller' => Controller\Api\RestorePasswordController::class,
                                'action'     => 'index',
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'request' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/request',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'post' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'request',
                                            ]
                                        ],
                                    ]
                                ]
                            ],
                            'new' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/new',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'new-get',
                                            ]
                                        ],
                                    ],
                                    'post' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'new-post',
                                            ]
                                        ],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'timezone' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/timezone',
                            'defaults' => [
                                'controller' => Controller\Api\TimezoneController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'list'
                                    ]
                                ]
                            ],
                        ]
                    ],
                    'traffic' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/traffic',
                            'defaults' => [
                                'controller' => Controller\Api\TrafficController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'list'
                                    ]
                                ]
                            ],
                            'whitelist' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/whitelist'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'list' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'whitelist-list'
                                            ]
                                        ]
                                    ],
                                    'create' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'whitelist-create'
                                            ]
                                        ]
                                    ],
                                    'item' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/:ip'
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'delete',
                                                    'defaults' => [
                                                        'action' => 'whitelist-item-delete'
                                                    ]
                                                ]
                                            ],
                                        ]
                                    ]
                                ]
                            ],
                            'blacklist' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/blacklist'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'create' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'blacklist-create'
                                            ]
                                        ]
                                    ],
                                    'item' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/:ip'
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'delete',
                                                    'defaults' => [
                                                        'action' => 'blacklist-item-delete'
                                                    ]
                                                ]
                                            ],
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'user' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/user',
                            'defaults' => [
                                'controller' => Controller\Api\UserController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'post' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ]
                                ]
                            ],
                            'user' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'item' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'put' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'put'
                                            ]
                                        ]
                                    ],
                                    'photo' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route'    => '/photo',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'delete',
                                                    'defaults' => [
                                                        'action' => 'delete-photo'
                                                    ]
                                                ]
                                            ],
                                            'post' => [
                                                'type' => 'Method',
                                                'options' => [
                                                    'verb' => 'post',
                                                    'defaults' => [
                                                        'action' => 'post-photo'
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'online' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/online',
                                    'defaults' => [
                                        'action' => 'online',
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'spec' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/spec',
                            'defaults' => [
                                'controller' => Controller\Api\SpecController::class
                            ]
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'get' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'stat' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/stat',
                            'defaults' => [
                                'controller' => Controller\Api\StatController::class
                            ]
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'global-summary' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/global-summary',
                                    'defaults' => [
                                        'action' => 'global-summary'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'vehicle-types' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/vehicle-types',
                            'defaults' => [
                                'controller' => Controller\Api\VehicleTypesController::class,
                                'action'     => 'index'
                            ],
                        ]
                    ],
                    'perspective' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/perspective',
                            'defaults' => [
                                'controller' => Controller\Api\PerspectiveController::class,
                                'action'     => 'index'
                            ],
                        ]
                    ],
                    'perspective-page' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/perspective-page',
                            'defaults' => [
                                'controller' => Controller\Api\PerspectivePageController::class,
                                'action'     => 'index'
                            ],
                        ]
                    ],
                    'picture-moder-vote-template' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/picture-moder-vote-template',
                            'defaults' => [
                                'controller' => Controller\Api\PictureModerVoteTemplateController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'create' => [
                                'type' => 'Method',
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'create'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'delete' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'delete',
                                            'defaults' => [
                                                'action' => 'delete'
                                            ]
                                        ]
                                    ],
                                    'get' => [
                                        'type' => 'Method',
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ],
                ]
            ]
        ]
    ]
];
