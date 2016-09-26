<?php

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;

use Zend\Permissions\Acl\Acl;
use Zend\ServiceManager\Factory\InvokableFactory;

use Autowp\TextStorage;

use Comment_Message;

use Picture;

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
        ]
    ],
    'controllers' => [
        'factories' => [
            Controller\Moder\AttrsController::class => InvokableFactory::class,
            Controller\Moder\BrandsController::class => function($sm) {
                $textStorage = $sm->get(TextStorage\Service::class);
                $logoForm = $sm->get('BrandLogoForm');
                $descForm = $sm->get('DescriptionForm');
                return new Controller\Moder\BrandsController($textStorage, $logoForm, $descForm);
            },
            Controller\Moder\CategoryController::class => function($sm) {
                return new Controller\Moder\CategoryController();
            },
            Controller\Moder\CarsController::class => function($sm) {
                $textStorage = $sm->get(TextStorage\Service::class);
                $translator = $sm->get('translator');
                $descForm = clone $sm->get('DescriptionForm');
                $textForm = clone $sm->get('DescriptionForm');
                $twinsForm = $sm->get('ModerTwinsGroup');
                $brandCarForm = $sm->get('ModerBrandCar');
                $carParentForm = $sm->get('ModerCarParent');
                $filterForm = $sm->get('ModerCarsFilter');
                return new Controller\Moder\CarsController($textStorage, $translator, $descForm, $textForm, $twinsForm, $brandCarForm, $carParentForm, $filterForm);
            },
            Controller\Moder\CommentsController::class => function($sm) {
                $form = $sm->get('ModerCommentsFilterForm');
                return new Controller\Moder\CommentsController($form);
            },
            Controller\Moder\EnginesController::class => function($sm) {
                $filterForm = $sm->get('ModerFactoryFilterForm');
                $editForm = $sm->get('ModerEngineForm');
                return new Controller\Moder\EnginesController($filterForm, $editForm);
            },
            Controller\Moder\FactoryController::class => function($sm) {
                $textStorage = $sm->get(TextStorage\Service::class);
                $addForm = $sm->get('ModerFactoryAddForm');
                $editForm = $sm->get('ModerFactoryEditForm');
                $descForm = $sm->get('DescriptionForm');
                $filterForm = $sm->get('ModerFactoryFilterForm');
                return new Controller\Moder\FactoryController($textStorage, $addForm, $editForm, $descForm, $filterForm);
            },
            Controller\Moder\HotlinkController::class => InvokableFactory::class,
            Controller\Moder\IndexController::class => function($sm) {
                $form = $sm->get('AddBrandForm');
                return new Controller\Moder\IndexController($form);
            },
            Controller\Moder\MuseumController::class => function($sm) {
                $form = $sm->get('MuseumForm');
                return new Controller\Moder\MuseumController($form);
            },
            Controller\Moder\PagesController::class => InvokableFactory::class,
            Controller\Moder\PerspectivesController::class => InvokableFactory::class,
            Controller\Moder\PicturesController::class => function($sm) {
                $table = $sm->get(Picture::class);
                $textStorage = $sm->get(TextStorage\Service::class);
                $pictureForm = $sm->get('ModerPictureForm');
                $copyrightsForm = $sm->get('ModerPictureCopyrightsForm');
                $voteForm = $sm->get('ModerPictureVoteForm');
                $banForm = $sm->get('BanForm');
                $translator = $sm->get('translator');
                return new Controller\Moder\PicturesController($table, $textStorage, $pictureForm, $copyrightsForm, $voteForm, $banForm, $translator);
            },
            Controller\Moder\RightsController::class => function($sm) {
                $acl = $sm->get(Acl::class);
                $cache = $sm->get('longCache');
                $roleForm = $sm->get('ModerAclRoleForm');
                $ruleForm = $sm->get('ModerAclRuleForm');
                $roleParentForm = $sm->get('ModerAclRoleParentForm');
                return new Controller\Moder\RightsController($acl, $cache, $roleForm, $ruleForm, $roleParentForm);
            },
            Controller\Moder\TrafficController::class => InvokableFactory::class,
            Controller\Moder\TwinsController::class => function($sm) {
                $textStorage = $sm->get(TextStorage\Service::class);
                $editForm = $sm->get('ModerTwinsEditForm');
                $descForm = $sm->get('DescriptionForm');
                return new Controller\Moder\TwinsController($textStorage, $editForm, $descForm);
            },
            Controller\Moder\UsersController::class => InvokableFactory::class,
        ]
    ],
    'forms' => [
        'ModerCommentsFilterForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'Новый Бренд',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'user',
                        'options' => [
                            'label'     => 'Пользователь №',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'brand_id',
                        'options' => [
                            'label'     => 'Бренд',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'moderator_attention',
                        'options' => [
                            'label'        => 'Внимание модераторов',
                            'options' => [
                                ''                                             => 'Не важно',
                                Comment_Message::MODERATOR_ATTENTION_NONE      => 'Не требуется',
                                Comment_Message::MODERATOR_ATTENTION_REQUIRED  => 'Требуется',
                                Comment_Message::MODERATOR_ATTENTION_COMPLETED => 'Выполнено',
                            ]
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'car_id',
                        'options' => [
                            'label'     => 'Автомобиль (id)',
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
                            'label'     => 'Название',
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
                        ['name' => Filter\SingleSpaces::class]
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
                'legend' => 'Добавить роль',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'role',
                        'options' => [
                            'label'     => 'Роль',
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
                            'label'     => 'Родительская роль',
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
                'legend' => 'Добавить правило',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'role_id',
                        'options' => [
                            'label'     => 'Роль',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'privilege_id',
                        'options' => [
                            'label'     => 'Привелегия',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'what',
                        'options' => [
                            'label'     => 'Действие',
                            'options' => [
                                '0' => 'запретить',
                                '1' => 'разрешить'
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
                'legend' => 'Добавить родителя',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'role_id',
                        'options' => [
                            'label'     => 'Роль',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'parent_role_id',
                        'options' => [
                            'label'     => 'Родительская роль',
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
                'legend' => 'Завод',
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
                            'label' => 'Год с'
                        ],
                        'attributes'  => [
                            'placeholder' => 'с'
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => Form\Element\Year::class,
                        'name' => 'year_to',
                        'options' => [
                            'label' => 'Год по'
                        ],
                        'attributes'  => [
                            'placeholder' => 'по'
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
                'legend' => 'Завод',
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
                            'label' => 'Год с'
                        ],
                        'attributes'  => [
                            'placeholder' => 'с'
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => Form\Element\Year::class,
                        'name' => 'year_to',
                        'options' => [
                            'label' => 'Год по'
                        ],
                        'attributes'  => [
                            'placeholder' => 'по'
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'lat',
                        'options' => [
                            'label' => 'Latitude'
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
                            'label' => 'Longtitude'
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
                'legend' => 'Завод',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'name',
                        'options' => [
                            'label' => 'Name',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'brand_id',
                        'options' => [
                            'label'        => 'Бренд',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'order',
                        'options' => [
                            'label'   => 'Сортировка',
                            'options' => [
                                0 => 'id asc',
                                1 => 'id desc',
                                2 => 'Название asc',
                                3 => 'Название desc',
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
                            'label' => 'Name',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'brand_id',
                        'options' => [
                            'label' => 'Бренд',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'order',
                        'options' => [
                            'label'   => 'Сортировка',
                            'options' => [
                                0 => 'id asc',
                                1 => 'id desc',
                                2 => 'Название asc',
                                3 => 'Название desc',
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
                'legend' => 'Двигатель',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => Form\Element\EngineName::class,
                        'name' => 'caption'
                    ],
                ]
            ],
            'input_filter' => [
                'caption' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim'],
                        ['name' => Filter\SingleSpaces::class]
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
                            'label' => 'Особое название'
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
                        ['name' => Filter\SingleSpaces::class]
                    ]
                ]
            ],
        ],
        'ModerPictureTypeForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'type',
                        'options' => [
                            'label' => 'Тип',
                            'options' => [
                                Picture::UNSORTED_TYPE_ID => 'Несортировано',
                                Picture::CAR_TYPE_ID      => 'Автомобиль',
                                Picture::LOGO_TYPE_ID     => 'Логотип',
                                Picture::MIXED_TYPE_ID    => 'Разное',
                                Picture::ENGINE_TYPE_ID   => 'Двигатель',
                                Picture::FACTORY_TYPE_ID  => 'Завод',
                            ]
                        ],
                        'attributes' => [
                            'class' => 'form-control'
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'type' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'Digits']
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
                            'label'     => 'Copyrights',
                        ],
                        'attributes' => [
                            'rows'       => 5
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
                            'label'     => 'Причина',
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
                                '1' => 'Хочу принять',
                                '0' => 'Хочу удалить'
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
                'legend' => 'Создание новой группы близнецов',
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
                        ['name' => Filter\SingleSpaces::class]
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
                        ['name' => Filter\SingleSpaces::class],
                        ['name' => 'StringToLower'],
                        ['name' => \Autowp\Filter\Filename\Safe::class]
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
                        ['name' => Filter\SingleSpaces::class],
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
                        ['name' => Filter\SingleSpaces::class],
                        ['name' => 'StringToLower'],
                        ['name' => \Autowp\Filter\Filename\Safe::class]
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
                            'label' => 'Name',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'no_name',
                        'options' => [
                            'label' => 'Name (исключить)',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'spec',
                        'options' => [
                            'label' => 'Spec',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'from_year',
                        'options' => [
                            'label' => 'From year',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'to_year',
                        'options' => [
                            'label' => 'To year',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'category',
                        'options' => [
                            'label' => 'Category',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'no_category',
                        'options' => [
                            'label' => 'Category (исключить)',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Checkbox',
                        'name'    => 'no_parent',
                        'options' => [
                            'label' => 'Без родителей',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'order',
                        'options' => [
                            'label' => 'Сортировка',
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