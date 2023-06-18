<?php

declare(strict_types=1);

namespace Application;

use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'hydrators'   => [
        'factories' => [
            Hydrator\Api\AttrConflictHydrator::class       => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\AttrUserValueHydrator::class      => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\AttrValueHydrator::class          => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\ItemHydrator::class               => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\ItemLanguageHydrator::class       => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\ItemParentHydrator::class         => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\ItemParentLanguageHydrator::class => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\LogHydrator::class                => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\PictureHydrator::class            => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\PictureItemHydrator::class        => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\SimilarHydrator::class            => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\UserHydrator::class               => Hydrator\Api\RestHydrator::class,
            Hydrator\Api\VotingVariantVoteHydrator::class  => Hydrator\Api\RestHydrator::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\Api\AccountController::class      => Controller\Api\AccountControllerFactory::class,
            Controller\Api\AttrController::class         => Controller\Api\AttrControllerFactory::class,
            Controller\Api\BrandsController::class       => Controller\Api\BrandsControllerFactory::class,
            Controller\Api\ChartController::class        => Controller\Api\ChartControllerFactory::class,
            Controller\Api\InboxController::class        => Controller\Api\InboxControllerFactory::class,
            Controller\Api\IndexController::class        => Controller\Api\IndexControllerFactory::class,
            Controller\Api\ItemController::class         => Controller\Api\ItemControllerFactory::class,
            Controller\Api\GalleryController::class      => Controller\Api\GalleryControllerFactory::class,
            Controller\Api\ItemLanguageController::class => Controller\Api\ItemLanguageControllerFactory::class,
            Controller\Api\ItemParentController::class   => Controller\Api\ItemParentControllerFactory::class,
            Controller\Api\ItemParentLanguageController::class
                => Controller\Api\ItemParentLanguageControllerFactory::class,
            Controller\Api\LogController::class              => Controller\Api\LogControllerFactory::class,
            Controller\Api\MostsController::class            => Controller\Api\MostsControllerFactory::class,
            Controller\Api\NewController::class              => Controller\Api\NewControllerFactory::class,
            Controller\Api\PictureController::class          => Controller\Api\PictureControllerFactory::class,
            Controller\Api\PictureItemController::class      => Controller\Api\PictureItemControllerFactory::class,
            Controller\Api\PictureModerVoteController::class => Controller\Api\PictureModerVoteControllerFactory::class,
            Controller\Api\RatingController::class           => Controller\Api\RatingControllerFactory::class,
            Controller\Api\StatController::class             => Controller\Api\StatControllerFactory::class,
            Controller\Api\TelegramController::class         => Controller\Api\TelegramControllerFactory::class,
            Controller\Api\TimezoneController::class         => InvokableFactory::class,
            Controller\Api\UserController::class             => Controller\Api\UserControllerFactory::class,
            Controller\Api\VotingController::class           => Controller\Api\VotingControllerFactory::class,
        ],
    ],
    'router'      => [
        'routes' => [
            'api' => [
                'type'          => 'Literal',
                'options'       => [
                    'route' => '/api',
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'account'            => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/account',
                            'defaults' => [
                                'controller' => Controller\Api\AccountController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'get'   => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'item'  => [
                                'type'          => 'Segment',
                                'options'       => [
                                    'route' => '/:id',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'delete' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'delete',
                                            'defaults' => [
                                                'action' => 'delete',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'start' => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/start',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'post' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'post',
                                            'defaults' => [
                                                'action' => 'start',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'attr'               => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/attr',
                            'defaults' => [
                                'controller' => Controller\Api\AttrController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'attribute'  => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/attribute',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get'  => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'attribute-index',
                                            ],
                                        ],
                                    ],
                                    'post' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'post',
                                            'defaults' => [
                                                'action' => 'attribute-post',
                                            ],
                                        ],
                                    ],
                                    'item' => [
                                        'type'          => 'Segment',
                                        'options'       => [
                                            'route' => '/:id',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'get'   => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'attribute-item-get',
                                                    ],
                                                ],
                                            ],
                                            'patch' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'patch',
                                                    'defaults' => [
                                                        'action' => 'attribute-item-patch',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'conflict'   => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/conflict',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'conflict-index',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'value'      => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/value',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'value-index',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'user-value' => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/user-value',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get'   => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'user-value-index',
                                            ],
                                        ],
                                    ],
                                    'patch' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'patch',
                                            'defaults' => [
                                                'action' => 'user-value-patch',
                                            ],
                                        ],
                                    ],
                                    'item'  => [
                                        'type'          => 'Segment',
                                        'options'       => [
                                            'route' => '/:attribute_id/:item_id/:user_id',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'delete' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'delete',
                                                    'defaults' => [
                                                        'action' => 'user-value-item-delete',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'brands'             => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/brands',
                            'defaults' => [
                                'controller' => Controller\Api\BrandsController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'get'  => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'item' => [
                                'type'          => 'Segment',
                                'options'       => [
                                    'route' => '/:id',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'sections'  => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route' => '/sections',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'get' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'sections',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'new-items' => [
                                        'type'          => 'Segment',
                                        'options'       => [
                                            'route' => '/new-items',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'get' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'new-items',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'chart'              => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/chart',
                            'defaults' => [
                                'controller' => Controller\Api\ChartController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'years'      => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/parameters',
                                    'defaults' => [
                                        'action' => 'parameters',
                                    ],
                                ],
                            ],
                            'years-data' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/data',
                                    'defaults' => [
                                        'action' => 'data',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'gallery'            => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route' => '/gallery',
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'get' => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'controller' => Controller\Api\GalleryController::class,
                                        'action'     => 'gallery',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'inbox'              => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/inbox',
                            'defaults' => [
                                'controller' => Controller\Api\InboxController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'get' => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'index'              => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/index',
                            'defaults' => [
                                'controller' => Controller\Api\IndexController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'spec-items'  => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/spec-items',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'spec-items',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'item-of-day' => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/item-of-day',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item-of-day',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'item'               => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/item',
                            'defaults' => [
                                'controller' => Controller\Api\ItemController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'list'  => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'post'  => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'post',
                                    'defaults' => [
                                        'action' => 'post',
                                    ],
                                ],
                            ],
                            'alpha' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/alpha',
                                    'defaults' => [
                                        'action' => 'alpha',
                                    ],
                                ],
                            ],
                            'path'  => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/path',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'path',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'item'  => [
                                'type'          => 'Segment',
                                'options'       => [
                                    'route'       => '/:id',
                                    'constraints' => [
                                        'id' => '[0-9]+',
                                    ],
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get'                  => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item',
                                            ],
                                        ],
                                    ],
                                    'put'                  => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'put',
                                            'defaults' => [
                                                'action' => 'put',
                                            ],
                                        ],
                                    ],
                                    'logo'                 => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route'    => '/logo',
                                            'defaults' => [
                                                'action' => 'logo',
                                            ],
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'get'  => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'get-logo',
                                                    ],
                                                ],
                                            ],
                                            'post' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'post',
                                                    'defaults' => [
                                                        'action' => 'post-logo',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'language'             => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route'    => '/language',
                                            'defaults' => [
                                                'controller' => Controller\Api\ItemLanguageController::class,
                                            ],
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'index' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'index',
                                                    ],
                                                ],
                                            ],
                                            'item'  => [
                                                'type'          => 'Segment',
                                                'options'       => [
                                                    'route' => '/:language',
                                                ],
                                                'may_terminate' => false,
                                                'child_routes'  => [
                                                    'get' => [
                                                        'type'    => 'Method',
                                                        'options' => [
                                                            'verb'     => 'get',
                                                            'defaults' => [
                                                                'action' => 'get',
                                                            ],
                                                        ],
                                                    ],
                                                    'put' => [
                                                        'type'    => 'Method',
                                                        'options' => [
                                                            'verb'     => 'put',
                                                            'defaults' => [
                                                                'action' => 'put',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'tree'                 => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route'    => '/tree',
                                            'defaults' => [
                                                'action' => 'tree',
                                            ],
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'get' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'tree',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'refresh-inheritance'  => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route' => '/refresh-inheritance',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'post' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'post',
                                                    'defaults' => [
                                                        'action' => 'refresh-inheritance',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'specifications'       => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route' => '/specifications',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'get' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'specifications',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'child-specifications' => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route' => '/child-specifications',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'get' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'child-specifications',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'new-items'            => [
                                        'type'          => 'Segment',
                                        'options'       => [
                                            'route' => '/new-items',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'get' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'new-items',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'item-parent'        => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/item-parent',
                            'defaults' => [
                                'controller' => Controller\Api\ItemParentController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'list' => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'item' => [
                                'type'          => 'Segment',
                                'options'       => [
                                    'route' => '/:item_id/:parent_id',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get'      => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item',
                                            ],
                                        ],
                                    ],
                                    'put'      => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'put',
                                            'defaults' => [
                                                'action' => 'put',
                                            ],
                                        ],
                                    ],
                                    'delete'   => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'delete',
                                            'defaults' => [
                                                'action' => 'delete',
                                            ],
                                        ],
                                    ],
                                    'language' => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route'    => '/language',
                                            'defaults' => [
                                                'controller' => Controller\Api\ItemParentLanguageController::class,
                                            ],
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'index' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'index',
                                                    ],
                                                ],
                                            ],
                                            'item'  => [
                                                'type'          => 'Segment',
                                                'options'       => [
                                                    'route' => '/:language',
                                                ],
                                                'may_terminate' => false,
                                                'child_routes'  => [
                                                    'get' => [
                                                        'type'    => 'Method',
                                                        'options' => [
                                                            'verb'     => 'get',
                                                            'defaults' => [
                                                                'action' => 'get',
                                                            ],
                                                        ],
                                                    ],
                                                    'put' => [
                                                        'type'    => 'Method',
                                                        'options' => [
                                                            'verb'     => 'put',
                                                            'defaults' => [
                                                                'action' => 'put',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'post' => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'post',
                                    'defaults' => [
                                        'action' => 'post',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'log'                => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/log',
                            'defaults' => [
                                'controller' => Controller\Api\LogController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'get' => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'mosts'              => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/mosts',
                            'defaults' => [
                                'controller' => Controller\Api\MostsController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'menu'  => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/menu',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'get-menu',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'items' => [
                                'type'          => 'Literal',
                                'options'       => [
                                    'route' => '/items',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'get-items',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'new'                => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/new',
                            'defaults' => [
                                'controller' => Controller\Api\NewController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'get' => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'picture'            => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/picture',
                            'defaults' => [
                                'controller' => Controller\Api\PictureController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'index'              => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'post'               => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'post',
                                    'defaults' => [
                                        'action' => 'post',
                                    ],
                                ],
                            ],
                            'picture'            => [
                                'type'          => 'Segment',
                                'options'       => [
                                    'route' => '/:id',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'accept-replace'     => [
                                        'type'    => 'Literal',
                                        'options' => [
                                            'route'    => '/accept-replace',
                                            'defaults' => [
                                                'action' => 'accept-replace',
                                            ],
                                        ],
                                    ],
                                    'normalize'          => [
                                        'type'    => 'Literal',
                                        'options' => [
                                            'route'    => '/normalize',
                                            'defaults' => [
                                                'action' => 'normalize',
                                            ],
                                        ],
                                    ],
                                    'flop'               => [
                                        'type'    => 'Literal',
                                        'options' => [
                                            'route'    => '/flop',
                                            'defaults' => [
                                                'action' => 'flop',
                                            ],
                                        ],
                                    ],
                                    'repair'             => [
                                        'type'    => 'Literal',
                                        'options' => [
                                            'route'    => '/repair',
                                            'defaults' => [
                                                'action' => 'repair',
                                            ],
                                        ],
                                    ],
                                    'correct-file-names' => [
                                        'type'    => 'Literal',
                                        'options' => [
                                            'route'    => '/correct-file-names',
                                            'defaults' => [
                                                'action' => 'correct-file-names',
                                            ],
                                        ],
                                    ],
                                    'similar'            => [
                                        'type'          => 'Segment',
                                        'options'       => [
                                            'route'       => '/similar/:similar_picture_id',
                                            'constraints' => [
                                                'similar_picture_id' => '[0-9]+',
                                            ],
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'delete' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'delete',
                                                    'defaults' => [
                                                        'action' => 'delete-similar',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'canonical-route'    => [
                                        'type'          => 'Segment',
                                        'options'       => [
                                            'route' => '/canonical-route',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'get' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'get',
                                                    'defaults' => [
                                                        'action' => 'canonical-route',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'item'               => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item',
                                            ],
                                        ],
                                    ],
                                    'update'             => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'put',
                                            'defaults' => [
                                                'action' => 'update',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'random_picture'     => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/random-picture',
                                    'defaults' => [
                                        'action' => 'random-picture',
                                    ],
                                ],
                            ],
                            'new-picture'        => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/new-picture',
                                    'defaults' => [
                                        'action' => 'new-picture',
                                    ],
                                ],
                            ],
                            'car-of-day-picture' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/car-of-day-picture',
                                    'defaults' => [
                                        'action' => 'car-of-day-picture',
                                    ],
                                ],
                            ],
                            'user-summary'       => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/user-summary',
                                    'defaults' => [
                                        'action' => 'user-summary',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'picture-moder-vote' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'       => '/picture-moder-vote/:id',
                            'constraints' => [
                                'id' => '[0-9]+',
                            ],
                            'defaults'    => [
                                'controller' => Controller\Api\PictureModerVoteController::class,
                            ],
                        ],
                    ],
                    'picture-item'       => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/picture-item',
                            'defaults' => [
                                'controller' => Controller\Api\PictureItemController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'get'  => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'item' => [
                                'type'          => 'Segment',
                                'options'       => [
                                    'route' => '/:picture_id/:item_id/:type',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'item'   => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item',
                                            ],
                                        ],
                                    ],
                                    'create' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'post',
                                            'defaults' => [
                                                'action' => 'create',
                                            ],
                                        ],
                                    ],
                                    'update' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'put',
                                            'defaults' => [
                                                'action' => 'update',
                                            ],
                                        ],
                                    ],
                                    'delete' => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'delete',
                                            'defaults' => [
                                                'action' => 'delete',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'rating'             => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/rating',
                            'defaults' => [
                                'controller' => Controller\Api\RatingController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'specs'         => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/specs',
                                    'defaults' => [
                                        'action' => 'specs',
                                    ],
                                ],
                            ],
                            'pictures'      => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/pictures',
                                    'defaults' => [
                                        'action' => 'pictures',
                                    ],
                                ],
                            ],
                            'likes'         => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/likes',
                                    'defaults' => [
                                        'action' => 'likes',
                                    ],
                                ],
                            ],
                            'picture-likes' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/picture-likes',
                                    'defaults' => [
                                        'action' => 'picture-likes',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'telegram-webhook'   => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/telegram/webhook/token/:token',
                            'defaults' => [
                                'controller' => Controller\Api\TelegramController::class,
                                'action'     => 'webhook',
                            ],
                        ],
                    ],
                    'timezone'           => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/timezone',
                            'defaults' => [
                                'controller' => Controller\Api\TimezoneController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'list' => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'list',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'user'               => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/user',
                            'defaults' => [
                                'controller' => Controller\Api\UserController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'list' => [
                                'type'    => 'Method',
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'user' => [
                                'type'          => 'Segment',
                                'options'       => [
                                    'route' => '/:id',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'item'  => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item',
                                            ],
                                        ],
                                    ],
                                    'put'   => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'put',
                                            'defaults' => [
                                                'action' => 'put',
                                            ],
                                        ],
                                    ],
                                    'photo' => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route' => '/photo',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'delete' => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'delete',
                                                    'defaults' => [
                                                        'action' => 'delete-photo',
                                                    ],
                                                ],
                                            ],
                                            'post'   => [
                                                'type'    => 'Method',
                                                'options' => [
                                                    'verb'     => 'post',
                                                    'defaults' => [
                                                        'action' => 'post-photo',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'stat'               => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/stat',
                            'defaults' => [
                                'controller' => Controller\Api\StatController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'global-summary' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/global-summary',
                                    'defaults' => [
                                        'action' => 'global-summary',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'voting'             => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/voting',
                            'defaults' => [
                                'controller' => Controller\Api\VotingController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'item' => [
                                'type'          => 'Segment',
                                'options'       => [
                                    'route' => '/:id',
                                ],
                                'may_terminate' => false,
                                'child_routes'  => [
                                    'get'     => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'get-item',
                                            ],
                                        ],
                                    ],
                                    'patch'   => [
                                        'type'    => 'Method',
                                        'options' => [
                                            'verb'     => 'patch',
                                            'defaults' => [
                                                'action' => 'patch-item',
                                            ],
                                        ],
                                    ],
                                    'variant' => [
                                        'type'          => 'Literal',
                                        'options'       => [
                                            'route' => '/variant',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes'  => [
                                            'item' => [
                                                'type'          => 'Segment',
                                                'options'       => [
                                                    'route' => '/:id',
                                                ],
                                                'may_terminate' => false,
                                                'child_routes'  => [
                                                    'vote' => [
                                                        'type'          => 'Literal',
                                                        'options'       => [
                                                            'route' => '/vote',
                                                        ],
                                                        'may_terminate' => false,
                                                        'child_routes'  => [
                                                            'get' => [
                                                                'type'    => 'Method',
                                                                'options' => [
                                                                    'verb'     => 'get',
                                                                    'defaults' => [
                                                                        'action' => 'get-vote-list',
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
