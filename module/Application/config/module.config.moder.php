<?php

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;

use Autowp\Comments;

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
                    'item-parent' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/item-parent[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\ItemParentController::class,
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
                    'modification' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/modification[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\ModificationController::class,
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
            Controller\Moder\ItemParentController::class   => Controller\Moder\Service\ItemParentControllerFactory::class,
            Controller\Moder\CarsController::class         => Controller\Moder\Service\CarsControllerFactory::class,
            Controller\Moder\CommentsController::class     => Controller\Moder\Service\CommentsControllerFactory::class,
            Controller\Moder\HotlinkController::class      => InvokableFactory::class,
            Controller\Moder\IndexController::class        => InvokableFactory::class,
            Controller\Moder\PagesController::class        => InvokableFactory::class,
            Controller\Moder\PerspectivesController::class => InvokableFactory::class,
            Controller\Moder\PictureItemController::class  => Controller\Moder\Service\PictureItemControllerFactory::class,
            Controller\Moder\PicturesController::class     => Controller\Moder\Service\PicturesControllerFactory::class,
            Controller\Moder\RightsController::class       => Controller\Moder\Service\RightsControllerFactory::class,
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
                        'type' => 'Select',
                        'name' => 'moderator_attention',
                        'options' => [
                            'label'   => 'moder/comments/filter/moderator_attention',
                            'options' => [
                                ''                            => 'moder/comments/filter/moderator_attention/not-matters',
                                Comments\Attention::NONE      => 'moder/comments/filter/moderator_attention/not-required',
                                Comments\Attention::REQUIRED  => 'moder/comments/filter/moderator_attention/required',
                                Comments\Attention::COMPLETED => 'moder/comments/filter/moderator_attention/resolved',
                            ]
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'item_id',
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
                'item_id' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
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
        'ModerBrandItem' => [
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
                            'label' => 'moder/items/filter/name',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'no_name',
                        'options' => [
                            'label' => 'moder/items/filter/name-exclude',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'item_type_id',
                        'options' => [
                            'label' => 'moder/items/filter/item-type',
                            'options' => [
                                '' => '--',
                                Model\DbTable\Item\Type::VEHICLE  => 'item/type/1/name',
                                Model\DbTable\Item\Type::ENGINE   => 'item/type/2/name',
                                Model\DbTable\Item\Type::CATEGORY => 'item/type/3/name',
                                Model\DbTable\Item\Type::TWINS    => 'item/type/4/name',
                                Model\DbTable\Item\Type::BRAND    => 'item/type/5/name',
                                Model\DbTable\Item\Type::FACTORY  => 'item/type/6/name',
                                Model\DbTable\Item\Type::MUSEUM   => 'item/type/7/name',
                            ]
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'vehicle_type_id',
                        'options' => [
                            'label' => 'moder/items/filter/vehicle-type',
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
                            'label' => 'moder/items/filter/spec',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'from_year',
                        'options' => [
                            'label' => 'moder/items/filter/from-year',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'to_year',
                        'options' => [
                            'label' => 'moder/items/filter/to-year',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'parent_id',
                        'options' => [
                            'label' => 'moder/items/filter/parent',
                        ]
                    ],
                ],
                /*[
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'no_category',
                        'options' => [
                            'label' => 'moder/items/filter/category-exclude',
                        ]
                    ],
                ],*/
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'text',
                        'options' => [
                            'label' => 'moder/items/filter/text',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Checkbox',
                        'name'    => 'no_parent',
                        'options' => [
                            'label' => 'moder/items/filter/no-parents',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'order',
                        'options' => [
                            'label' => 'moder/items/filter/order',
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
                'item_type_id' => [
                    'required' => false
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
                'parent_id' => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                        ['name' => 'Digits'],
                    ]
                ],
                /*'no_category' => [
                    'required'   => false,
                ],*/
                'no_parent' => [
                    'required'   => false,
                ],
                'order' => [
                    'required'   => false,
                ],
                'text' => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ]
                ],
            ],
        ],
    ]
];