<?php

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;

use Application\Model\DbTable\Comment\Message as CommentMessage;

return [
    'router' => [
        'routes' => [
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
                    'attrs' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/attrs[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\AttrsController::class,
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
                    'brand-vehicle' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/brand-vehicle[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\BrandVehicleController::class,
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
                                'controller' => Controller\Moder\CarsController::class,
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
                    'picture-item' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/picture-item[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\PictureItemController::class,
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
                    'twins' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/twins[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\TwinsController::class,
                                'action'     => 'index'
                            ],
                        ],
                        'may_terminate' => false,
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
        ]
    ],
    'controllers' => [
        'factories' => [
            Controller\Moder\AttrsController::class        => InvokableFactory::class,
            Controller\Moder\BrandsController::class       => Controller\Moder\Service\BrandsControllerFactory::class,
            Controller\Moder\CategoryController::class     => Controller\Moder\Service\CategoryControllerFactory::class,
            Controller\Moder\BrandVehicleController::class => Controller\Moder\Service\BrandVehicleControllerFactory::class,
            Controller\Moder\CarsController::class         => Controller\Moder\Service\CarsControllerFactory::class,
            Controller\Moder\CommentsController::class     => Controller\Moder\Service\CommentsControllerFactory::class,
            Controller\Moder\EnginesController::class      => Controller\Moder\Service\EnginesControllerFactory::class,
            Controller\Moder\FactoryController::class      => Controller\Moder\Service\FactoryControllerFactory::class,
            Controller\Moder\HotlinkController::class      => InvokableFactory::class,
            Controller\Moder\IndexController::class        => Controller\Moder\Service\IndexControllerFactory::class,
            Controller\Moder\MuseumController::class       => Controller\Moder\Service\MuseumControllerFactory::class,
            Controller\Moder\PagesController::class        => InvokableFactory::class,
            Controller\Moder\PerspectivesController::class => InvokableFactory::class,
            Controller\Moder\PictureItemController::class  => Controller\Moder\Service\PictureItemControllerFactory::class,
            Controller\Moder\PicturesController::class     => Controller\Moder\Service\PicturesControllerFactory::class,
            Controller\Moder\RightsController::class       => Controller\Moder\Service\RightsControllerFactory::class,
            Controller\Moder\TwinsController::class        => Controller\Moder\Service\TwinsControllerFactory::class,
            Controller\Moder\UsersController::class        => InvokableFactory::class,
        ]
    ],
    'forms' => [
        'ModerCarForm' => [
            'type' => Form\Moder\Car::class,
            'attributes'  => [
                'method' => 'post',
            ],
        ],
        'ModerCarOrganizeForm' => [
            'type' => Form\Moder\CarOrganize::class,
            'attributes'  => [
                'method' => 'post',
            ],
        ],
        'ModerCarOrganizePicturesForm' => [
            'type' => Form\Moder\CarOrganizePictures::class,
            'attributes'  => [
                'method' => 'post',
            ],
        ],
        'ModerCommentsFilterForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'user',
                        'options' => [
                            'label' => 'moder/comments/filter/user-id',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'brand_id',
                        'options' => [
                            'label' => 'moder/comments/filter/brand-id',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'moderator_attention',
                        'options' => [
                            'label'   => 'moder/comments/filter/moderator_attention',
                            'options' => [
                                ''                                            => 'moder/comments/filter/moderator_attention/not-matters',
                                CommentMessage::MODERATOR_ATTENTION_NONE      => 'moder/comments/filter/moderator_attention/not-required',
                                CommentMessage::MODERATOR_ATTENTION_REQUIRED  => 'moder/comments/filter/moderator_attention/required',
                                CommentMessage::MODERATOR_ATTENTION_COMPLETED => 'moder/comments/filter/moderator_attention/resolved',
                            ]
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'car_id',
                        'options' => [
                            'label' => 'moder/comments/filter/vehicle-id',
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'user' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'moderator_attention' => [
                    'required' => false,
                ],
                'brand_id' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'car_id' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ]
            ]
        ],
        'ModerTwinsEditForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'name',
                        'options' => [
                            'label'     => 'twins/group/name',
                            'maxlength' => 255,
                            'size'      => 80,
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim'],
                        ['name' => 'SingleSpaces']
                    ],
                    'validators' => [
                        [
                            'name' => 'StringLength',
                            'options' => [
                                'min' => 1,
                                'max' => 255
                            ]
                        ]
                    ]
                ]
            ]
        ],
        'ModerAclRoleForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/acl/add-role'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'role',
                        'options' => [
                            'label' => 'moder/acl/role',
                        ],
                        'attributes' => [
                            'maxlength' => 80
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'parent_role_id',
                        'options' => [
                            'label' => 'moder/acl/parent-role',
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'role' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'parent_role_id' => [
                    'required' => true
                ]
            ]
        ],
        'ModerAclRuleForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/acl/add-rule',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'role_id',
                        'options' => [
                            'label' => 'moder/acl/role',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'privilege_id',
                        'options' => [
                            'label' => 'moder/acl/privilege',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'what',
                        'options' => [
                            'label'   => 'moder/acl/add-rule/action',
                            'options' => [
                                '0' => 'moder/acl/add-rule/action/deny',
                                '1' => 'moder/acl/add-rule/action/allow'
                            ]
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'role_id' => [
                    'required' => true
                ],
                'privilege_id' => [
                    'required' => true
                ],
                'what' => [
                    'required' => true
                ]
            ]
        ],
        'ModerAclRoleParentForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/acl/add-parent',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'role_id',
                        'options' => [
                            'label' => 'moder/acl/role',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'parent_role_id',
                        'options' => [
                            'label' => 'moder/acl/parent-role',
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'role_id' => [
                    'required' => true
                ],
                'parent_role_id' => [
                    'required' => true
                ]
            ]
        ],
        'ModerFactoryAddForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/factories/add/title'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => Form\Element\FactoryName::class,
                        'name' => 'name'
                    ],
                ],
                [
                    'spec' => [
                        'type' => Form\Element\Year::class,
                        'name' => 'year_from',
                        'options' => [
                            'label' => 'factory/year_from'
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => Form\Element\Year::class,
                        'name' => 'year_to',
                        'options' => [
                            'label' => 'factory/year_to'
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required' => true
                ],
                'year_from' => [
                    'required' => false
                ],
                'year_to' => [
                    'required' => false
                ],
            ],
        ],
        'ModerFactoryEditForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/factories/edit/title'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => Form\Element\FactoryName::class,
                        'name' => 'name'
                    ],
                ],
                [
                    'spec' => [
                        'type' => Form\Element\Year::class,
                        'name' => 'year_from',
                        'options' => [
                            'label' => 'factory/year_from'
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => Form\Element\Year::class,
                        'name' => 'year_to',
                        'options' => [
                            'label' => 'factory/year_to'
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'lat',
                        'options' => [
                            'label' => 'latitude'
                        ],
                        'attributes' => [
                            'id'        => 'lat',
                            'maxlength' => 20,
                            'size'      => 20,
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'lng',
                        'options' => [
                            'label' => 'longtitude'
                        ],
                        'attributes' => [
                            'id'        => 'lng',
                            'maxlength' => 20,
                            'size'      => 20,
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required' => true
                ],
                'year_from' => [
                    'required' => false
                ],
                'year_to' => [
                    'required' => false
                ],
                'lat' => [
                    'required' => false,
                    'filters' => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'lng' => [
                    'required' => false,
                    'filters' => [
                        ['name' => 'StringTrim']
                    ]
                ]
            ],
        ],
        'ModerFactoryFilterForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/factories/filter'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'name',
                        'options' => [
                            'label' => 'factory/name'
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'brand_id',
                        'options' => [
                            'label' => 'moder/factories/filter/brand-id'
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'order',
                        'options' => [
                            'label'   => 'moder/factories/filter/order',
                            'options' => [
                                0 => 'id asc',
                                1 => 'id desc',
                                2 => 'moder/factories/filter/order/name-asc',
                                3 => 'moder/factories/filter/order/name-desc',
                            ]
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required' => false
                ],
                'brand_id' => [
                    'required' => false
                ],
                'order' => [
                    'required' => false
                ],
            ],
        ],
        'ModerEnginesFilterForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'name',
                        'options' => [
                            'label' => 'moder/engines/filter/name',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'brand_id',
                        'options' => [
                            'label' => 'moder/engines/filter/brand-id',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'order',
                        'options' => [
                            'label'   => 'moder/engines/filter/order',
                            'options' => [
                                0 => 'id asc',
                                1 => 'id desc',
                                2 => 'moder/engines/filter/order/name-asc',
                                3 => 'moder/engines/filter/order/name-desc',
                            ]
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required' => false
                ],
                'brand_id' => [
                    'required' => false
                ],
                'order' => [
                    'required' => false
                ],
            ],
        ],
        'ModerEngineForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/engines/engine/title'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'EngineName',
                        'name' => 'name'
                    ],
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim'],
                        ['name' => 'SingleSpaces']
                    ],
                    'validators' => [
                        [
                            'name' => 'StringLength',
                            'options' => [
                                'max' => Model\DbTable\Engine::MAX_NAME
                            ]
                        ]
                    ]
                ]
            ],
        ],
        'ModerPictureForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'name',
                        'options' => [
                            'label' => 'moder/picture/edit/special-name'
                        ],
                        'attributes' => [
                            'size'      => 60,
                            'maxlength' => 255,
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim'],
                        ['name' => 'SingleSpaces']
                    ]
                ]
            ],
        ],
        'ModerPictureCopyrightsForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Textarea',
                        'name' => 'text',
                        'options' => [
                            'label' => 'Copyrights',
                        ],
                        'attributes' => [
                            'rows' => 5
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'text' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        [
                            'name' => 'StringLength',
                            'options' => [
                                'min' => 0,
                                'max' => 4096
                            ]
                        ]
                    ]
                ]
            ]
        ],
        'ModerPictureVoteForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'reason',
                        'options' => [
                            'label' => 'moder/picture/acceptance/reason',
                        ],
                        'attributes' => [
                            'size'      => 60,
                            'maxlength' => 255,
                            'class'     => 'form-control',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'vote',
                        'options' => [
                            'options' => [
                                '1' => 'moder/picture/acceptance/want-accept',
                                '0' => 'moder/picture/acceptance/want-delete'
                            ]
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'reason' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'vote' => [
                    'required' => true
                ]
            ]
        ],
        'ModerTwinsGroup' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/twins/add/title',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'name',
                        'options' => [
                            'label' => 'moder/twins/name',
                        ],
                        'attributes' => [
                            'maxlength'  => 255,
                            'size'       => 80,
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim'],
                        ['name' => 'SingleSpaces']
                    ],
                    'validators' => [
                        [
                            'name' => 'StringLength',
                            'options' => [
                                'min' => 1,
                                'max' => 255
                            ]
                        ]
                    ]
                ]
            ],
        ],
        'ModerBrandEdit' => [
            'type' => Form\Moder\Brand\Edit::class,
            'attributes'  => [
                'method' => 'post',
            ],
        ],
        'ModerBrandCar' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'catname',
                        'options' => [
                            'label' => 'Catname',
                        ],
                        'attributes' => [
                            'maxlength' => 50
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'catname' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim'],
                        ['name' => 'SingleSpaces'],
                        ['name' => 'StringToLower'],
                        ['name' => 'FilenameSafe']
                    ],
                    'validators' => [
                        [
                            'name' => 'StringLength',
                            'options' => [
                                'min' => 1,
                                'max' => 50
                            ]
                        ]
                    ]
                ]
            ],
        ],
        'ModerCarParent' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'name',
                        'options' => [
                            'label' => 'Name',
                        ],
                        'attributes' => [
                            'maxlength' => 50
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'catname',
                        'options' => [
                            'label' => 'Catname',
                        ],
                        'attributes' => [
                            'maxlength' => 50
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                        ['name' => 'SingleSpaces'],
                    ],
                    'validators' => [
                        [
                            'name' => 'StringLength',
                            'options' => [
                                'min' => 1,
                                'max' => 50
                            ]
                        ]
                    ]
                ],
                'catname' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim'],
                        ['name' => 'SingleSpaces'],
                        ['name' => 'StringToLower'],
                        ['name' => 'FilenameSafe']
                    ],
                    'validators' => [
                        [
                            'name' => 'StringLength',
                            'options' => [
                                'min' => 1,
                                'max' => 50
                            ]
                        ]
                    ]
                ]
            ],
        ],
        'ModerCarsFilter' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'name',
                        'options' => [
                            'label' => 'moder/vehicles/filter/name',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'no_name',
                        'options' => [
                            'label' => 'moder/vehicles/filter/name-exclude',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'vehicle_type_id',
                        'options' => [
                            'label' => 'moder/vehicles/filter/vehicle-type',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'vehicle_childs_type_id',
                        'options' => [
                            'label' => 'Have childs with type',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'spec',
                        'options' => [
                            'label' => 'moder/vehicles/filter/spec',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'from_year',
                        'options' => [
                            'label' => 'moder/vehicles/filter/from-year',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'to_year',
                        'options' => [
                            'label' => 'moder/vehicles/filter/to-year',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'category',
                        'options' => [
                            'label' => 'moder/vehicles/filter/category',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'no_category',
                        'options' => [
                            'label' => 'moder/vehicles/filter/category-exclude',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Checkbox',
                        'name'    => 'no_parent',
                        'options' => [
                            'label' => 'moder/vehicles/filter/no-parents',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'order',
                        'options' => [
                            'label' => 'moder/vehicles/filter/order',
                            'options' => [
                                0 => 'id asc',
                                1 => 'id desc',
                            ]
                        ]
                    ],
                ],
            ],
            'input_filter' => [
                'name' => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ]
                ],
                'no_name' => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ]
                ],
                'vehicle_type_id' => [
                    'required'   => false,
                ],
                'vehicle_childs_type_id' => [
                    'required'   => false,
                ],
                'spec' => [
                    'required'   => false,
                ],
                'from_year' => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                        ['name' => 'Digits'],
                    ]
                ],
                'to_year' => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                        ['name' => 'Digits'],
                    ]
                ],
                'category' => [
                    'required'   => false,
                ],
                'no_category' => [
                    'required'   => false,
                ],
                'no_parent' => [
                    'required'   => false,
                ],
                'order' => [
                    'required'   => false,
                ],
            ],
        ],
    ]
];