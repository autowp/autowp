<?php

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Method;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;

use Application\Model\DbTable;
use Autowp\ZFComponents\Filter\SingleSpaces;

return [
    'hydrators' => [
        'factories' => [
            Hydrator\Api\CommentHydrator::class          => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\IpHydrator::class               => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemHydrator::class             => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemParentHydrator::class       => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\LogHydrator::class              => Hydrator\Api\RestHydratorFactory::class,
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
            Controller\Api\CommentController::class         => Controller\Api\Service\CommentControllerFactory::class,
            Controller\Api\ContactsController::class        => InvokableFactory::class,
            Controller\Api\HotlinksController::class        => InvokableFactory::class,
            Controller\Api\IpController::class              => Controller\Api\Service\IpControllerFactory::class,
            Controller\Api\ItemController::class            => Controller\Api\Service\ItemControllerFactory::class,
            Controller\Api\ItemParentController::class      => Controller\Api\Service\ItemParentControllerFactory::class,
            Controller\Api\ItemVehicleTypeController::class => Controller\Api\Service\ItemVehicleTypeControllerFactory::class,
            Controller\Api\LogController::class             => Controller\Api\Service\LogControllerFactory::class,
            Controller\Api\PageController::class            => Controller\Api\Service\PageControllerFactory::class,
            Controller\Api\PerspectiveController::class     => Controller\Api\Service\PerspectiveControllerFactory::class,
            Controller\Api\PerspectivePageController::class => Controller\Api\Service\PerspectivePageControllerFactory::class,
            Controller\Api\PictureController::class         => Controller\Api\Service\PictureControllerFactory::class,
            Controller\Api\PictureItemController::class     => Controller\Api\Service\PictureItemControllerFactory::class,
            Controller\Api\PictureModerVoteController::class => Controller\Api\Service\PictureModerVoteControllerFactory::class,
            Controller\Api\PictureModerVoteTemplateController::class => Controller\Api\Service\PictureModerVoteTemplateControllerFactory::class,
            Controller\Api\PictureVoteController::class     => Controller\Api\Service\PictureVoteControllerFactory::class,
            Controller\Api\SpecController::class            => InvokableFactory::class,
            Controller\Api\StatController::class            => InvokableFactory::class,
            Controller\Api\TrafficController::class         => Controller\Api\Service\TrafficControllerFactory::class,
            Controller\Api\UsersController::class           => InvokableFactory::class,
            Controller\Api\UserController::class            => Controller\Api\Service\UserControllerFactory::class,
            Controller\Api\VehicleTypesController::class    => InvokableFactory::class,
        ]
    ],
    'input_filter_specs' => [
        'api_acl_roles_list' => [
            'recursive' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
        ],
        'api_acl_roles_post' => [
            'name' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
        ],
        'api_acl_roles_role_parents_post' => [
            'role' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
        ],
        'api_acl_rules_post' => [
            'role' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'resource' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'privilege' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'allowed' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ]
        ],
        'api_ip_item' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['hostname', 'rights', 'blacklist']]
                    ]
                ]
            ],
        ],
        'api_item_list' => [
            'last_item' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'descendant' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'parent_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'type_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                DbTable\Item\Type::VEHICLE,
                                DbTable\Item\Type::ENGINE,
                                DbTable\Item\Type::CATEGORY,
                                DbTable\Item\Type::TWINS,
                                DbTable\Item\Type::BRAND,
                                DbTable\Item\Type::FACTORY,
                                DbTable\Item\Type::MUSEUM,
                            ]
                        ]
                    ]
                ]
            ],
            'vehicle_type_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'vehicle_childs_type_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ]
            ],
            'spec' => [
                'required'   => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
            ],
            'limit' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1,
                            'max' => 500
                        ]
                    ]
                ]
            ],
            'page' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true
                        ]
                    ]
                ]
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['childs_count', 'name_html',
                            'name_text', 'name_default', 'description',
                            'has_text', 'brands', 'moder_url', 'upload_url',
                            'spec_editor_url', 'specs_url', 'categories',
                            'twins_groups', 'url', 'more_pictures_url',
                            'preview_pictures', 'design', 'engine_vehicles',
                            'catname', 'is_concept', 'spec_id', 'begin_year', 'end_year']]
                    ]
                ]
            ],
            'order' => [
                'required'   => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'name',
                                'childs_count',
                                'id_desc',
                                'id_asc'
                            ]
                        ]
                    ]
                ]
            ],
            'name' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'name_exclude' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'no_parent' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'text' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'from_year' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'to_year' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ]
        ],
        'api_item_item' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['childs_count', 'name_html',
                            'name_text', 'name_default', 'description',
                            'has_text', 'brands', 'moder_url', 'upload_url',
                            'spec_editor_url', 'specs_url', 'categories',
                            'twins_groups', 'url', 'more_pictures_url',
                            'preview_pictures', 'design', 'engine_vehicles',
                            'catname', 'is_concept', 'spec_id', 'begin_year', 'end_year']]
                    ]
                ]
            ],
        ],
        'api_item_parent_list' => [
            'ancestor_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'concept' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
            ],
            'item_type_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'parent_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'limit' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1,
                            'max' => 500
                        ]
                    ]
                ]
            ],
            'page' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true
                        ]
                    ]
                ]
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['item']]
                    ]
                ]
            ],
            'order' => [
                'required'   => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'name',
                                'childs_count'
                            ]
                        ]
                    ]
                ]
            ],
        ],
        'api_item_parent_item' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['item']]
                    ]
                ]
            ]
        ],
        'api_item_parent_post' => [
            'item_id' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'parent_id' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
        ],
        'api_log_list' => [
            'article_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'item_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'picture_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'user_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'page' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true
                        ]
                    ]
                ]
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['user', 'pictures', 'items']]
                    ]
                ]
            ]
        ],
        'api_page_post' => [
            'parent_id' => [
                'required' => false
            ],
            'name' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_NAME
                        ]
                    ]
                ]
            ],
            'title' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_TITLE
                        ]
                    ]
                ]
            ],
            'breadcrumbs' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_BREADCRUMBS
                        ]
                    ]
                ]
            ],
            'url' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_URL
                        ]
                    ]
                ]
            ],
            'is_group_node' => [
                'required' => false
            ],
            'registered_only' => [
                'required' => false
            ],
            'guest_only' => [
                'required' => false
            ],
            'class' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_CLASS
                        ]
                    ]
                ]
            ]
        ],
        'api_page_put' => [
            'parent_id' => [
                'required' => false
            ],
            'name' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_NAME
                        ]
                    ]
                ]
            ],
            'title' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_TITLE
                        ]
                    ]
                ]
            ],
            'breadcrumbs' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_BREADCRUMBS
                        ]
                    ]
                ]
            ],
            'url' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_URL
                        ]
                    ]
                ]
            ],
            'is_group_node' => [
                'required' => false
            ],
            'registered_only' => [
                'required' => false
            ],
            'guest_only' => [
                'required' => false
            ],
            'class' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\Page::MAX_CLASS
                        ]
                    ]
                ]
            ],
            'position' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'up',
                                'down'
                            ]
                        ]
                    ]
                ]
            ]
        ],
        'api_perspective_page_list' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['groups']]
                    ]
                ]
            ]
        ],
        'api_picture_list' => [
            'limit' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1,
                            'max' => 500
                        ]
                    ]
                ]
            ],
            'page' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true
                        ]
                    ]
                ]
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => [
                            'owner', 'thumbnail', 'moder_vote', 'votes',
                            'similar', 'comments_count', 'add_date', 'iptc',
                            'exif', 'image', 'items', 'special_name',
                            'copyrights', 'change_status_user', 'rights',
                            'moder_votes', 'moder_voted', 'is_last',
                            'accepted_count', 'crop', 'replaceable',
                            'perspective_item', 'siblings', 'ip',
                            'name_html', 'name_text'
                        ]]
                    ]
                ]
            ],
            'status' => [
                'required' => false
            ],
            'car_type_id' => [
                'required' => false
            ],
            'perspective_id' => [
                'required' => false
            ],
            'item_id' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'type_id' => [
                'required' => false
            ],
            'comments' => [
                'required' => false
            ],
            'owner_id' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'replace' => [
                'required' => false
            ],
            'requests' => [
                'required' => false
            ],
            'special_name' => [
                'required' => false
            ],
            'lost' => [
                'required' => false
            ],
            'gps' => [
                'required' => false
            ],
            'order' => [
                'required' => false
            ],
            'similar' => [
                'required' => false
            ]
        ],
        'api_picture_edit' => [
            'status' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                DbTable\Picture::STATUS_INBOX,
                                DbTable\Picture::STATUS_ACCEPTED,
                                DbTable\Picture::STATUS_REMOVING,
                            ]
                        ]
                    ]
                ]
            ],
            'special_name' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => DbTable\Picture::MAX_NAME,
                        ]
                    ]
                ]
            ],
            'copyrights' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => 65536,
                        ]
                    ]
                ]
            ],
            'crop' => [
                'required' => false,
                'left' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        ['name' => 'Digits']
                    ]
                ],
                'top' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        ['name' => 'Digits']
                    ]
                ],
                'width' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        ['name' => 'Digits']
                    ]
                ],
                'height' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        ['name' => 'Digits']
                    ]
                ]
            ],
            'replace_picture_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ]
        ],
        'api_picture_item' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => [
                            'owner', 'thumbnail', 'moder_vote', 'votes',
                            'similar', 'comments_count', 'add_date', 'iptc',
                            'exif', 'image', 'items', 'special_name',
                            'copyrights', 'change_status_user', 'rights',
                            'moder_votes', 'moder_voted', 'is_last',
                            'accepted_count', 'crop', 'replaceable',
                            'perspective_item', 'siblings', 'ip',
                            'name_html', 'name_text'
                        ]]
                    ]
                ]
            ]
        ],
        'api_picture_item_item' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => [
                            'area'
                        ]]
                    ]
                ]
            ]
        ],
        'api_picture_moder_vote_template_list' => [
            'name' => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
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
            'vote' => [
                'required'   => true,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [-1, 1]
                        ]
                    ]
                ]
            ]
        ],
        'api_user_list' => [
            'limit' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1,
                            'max' => 500
                        ]
                    ]
                ]
            ],
            'page' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true
                        ]
                    ]
                ]
            ],
            'search' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ]
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => []]
                    ]
                ]
            ]
        ],
    ],
    'router' => [
        'routes' => [
            'api' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/api',
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'acl' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/acl',
                            'defaults' => [
                                'controller' => Controller\Api\AclController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'inherit-roles' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/inherit-roles',
                                    'defaults' => [
                                        'action' => 'inherit-roles'
                                    ],
                                ]
                            ],
                            'is-allowed' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/is-allowed',
                                    'defaults' => [
                                        'action' => 'is-allowed'
                                    ],
                                ]
                            ],
                            'roles' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/roles'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'roles'
                                            ]
                                        ]
                                    ],
                                    'post' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'roles-post'
                                            ]
                                        ]
                                    ],
                                    'role' => [
                                        'type' => Segment::class,
                                        'options' => [
                                            'route' => '/:role',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'get' => [
                                                'type' => Method::class,
                                                'options' => [
                                                    'verb' => 'get',
                                                    'defaults' => [
                                                        'action' => 'role'
                                                    ]
                                                ]
                                            ],
                                            'parents' => [
                                                'type' => Literal::class,
                                                'options' => [
                                                    'route'    => '/parents',
                                                    'defaults' => [
                                                        'action' => 'role-parents'
                                                    ],
                                                ],
                                                'may_terminate' => false,
                                                'child_routes' => [
                                                    'get' => [
                                                        'type' => Method::class,
                                                        'options' => [
                                                            'verb' => 'get',
                                                            'defaults' => [
                                                                'action' => 'role-parents'
                                                            ]
                                                        ]
                                                    ],
                                                    'post' => [
                                                        'type' => Method::class,
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
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/resources',
                                    'defaults' => [
                                        'action' => 'resources'
                                    ],
                                ]
                            ],
                            'rules' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/rules'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'rules'
                                            ]
                                        ]
                                    ],
                                    'post' => [
                                        'type' => Method::class,
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
                    'comment' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/comment',
                            'defaults' => [
                                'controller' => Controller\Api\CommentController::class,
                                'action'     => 'index'
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'subscribe' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/subscribe/:type_id/:item_id',
                                    'defaults' => [
                                        'action' => 'subscribe'
                                    ],
                                ],
                            ]
                        ]
                    ],
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
                    'hotlinks' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/hotlinks',
                            'defaults' => [
                                'controller' => Controller\Api\HotlinksController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'blacklist' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/blacklist'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'post' => [
                                        'type' => Method::class,
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
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/whitelist'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'post' => [
                                        'type' => Method::class,
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
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/hosts'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'hosts'
                                            ]
                                        ]
                                    ],
                                    'delete' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'delete',
                                            'defaults' => [
                                                'action' => 'hosts-delete'
                                            ]
                                        ]
                                    ],
                                    'host' => [
                                        'type' => Segment::class,
                                        'options' => [
                                            'route' => '/:host',
                                            'defaults' => [
                                                'action' => 'host'
                                            ]
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => Method::class,
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
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/ip/:ip',
                            'defaults' => [
                                'controller' => Controller\Api\IpController::class
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'item' => [
                                'type' => Method::class,
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
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/item',
                            'defaults' => [
                                'controller' => Controller\Api\ItemController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'post' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ]
                                ]
                            ],
                            'alpha' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/alpha',
                                    'defaults' => [
                                        'action' => 'alpha'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:id',
                                    'constraints' => [
                                        'id' => '[0-9]+'
                                    ],
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => Method::class,
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
                    'item-parent' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/item-parent',
                            'defaults' => [
                                'controller' => Controller\Api\ItemParentController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:item_id/:parent_id',
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb'     => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'post' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb'     => 'post',
                                            'defaults' => [
                                                'action' => 'post'
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ],
                    'item-vehicle-type' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/item-vehicle-type/:item_id/:vehicle_type_id',
                            'defaults' => [
                                'controller' => Controller\Api\ItemVehicleTypeController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'item' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'item'
                                    ]
                                ]
                            ],
                            'create' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb'     => 'post',
                                    'defaults' => [
                                        'action' => 'create'
                                    ]
                                ]
                            ],
                            'delete' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb'     => 'delete',
                                    'defaults' => [
                                        'action' => 'delete'
                                    ]
                                ]
                            ],
                        ]
                    ],
                    'log' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/log',
                            'defaults' => [
                                'controller' => Controller\Api\LogController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'get' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'page' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/page',
                            'defaults' => [
                                'controller' => Controller\Api\PageController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'post' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'post'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:id'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'get' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'put' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'item-put'
                                            ]
                                        ]
                                    ],
                                    'delete' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'delete',
                                            'defaults' => [
                                                'action' => 'item-delete'
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
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
                            'index' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'picture' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:id'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'accept-replace' => [
                                        'type' => Literal::class,
                                        'options' => [
                                            'route'    => '/accept-replace',
                                            'defaults' => [
                                                'action' => 'accept-replace'
                                            ],
                                        ],
                                    ],
                                    'normalize' => [
                                        'type' => Literal::class,
                                        'options' => [
                                            'route'    => '/normalize',
                                            'defaults' => [
                                                'action' => 'normalize'
                                            ],
                                        ],
                                    ],
                                    'flop' => [
                                        'type' => Literal::class,
                                        'options' => [
                                            'route'    => '/flop',
                                            'defaults' => [
                                                'action' => 'flop'
                                            ],
                                        ],
                                    ],
                                    'repair' => [
                                        'type' => Literal::class,
                                        'options' => [
                                            'route'    => '/repair',
                                            'defaults' => [
                                                'action' => 'repair'
                                            ],
                                        ],
                                    ],
                                    'correct-file-names' => [
                                        'type' => Literal::class,
                                        'options' => [
                                            'route'    => '/correct-file-names',
                                            'defaults' => [
                                                'action' => 'correct-file-names'
                                            ],
                                        ],
                                    ],
                                    'similar' => [
                                        'type' => Segment::class,
                                        'options' => [
                                            'route' => '/similar/:similar_picture_id',
                                            'constraints' => [
                                                'similar_picture_id' => '[0-9]+'
                                            ],
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => Method::class,
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
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'item'
                                            ]
                                        ]
                                    ],
                                    'update' => [
                                        'type' => Method::class,
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
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/random-picture',
                                    'defaults' => [
                                        'action' => 'random-picture'
                                    ],
                                ]
                            ],
                            'new-picture' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/new-picture',
                                    'defaults' => [
                                        'action' => 'new-picture'
                                    ],
                                ]
                            ],
                            'car-of-day-picture' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/car-of-day-picture',
                                    'defaults' => [
                                        'action' => 'car-of-day-picture'
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'picture-moder-vote' => [
                        'type' => Segment::class,
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
                        'type' => Segment::class,
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
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/picture-item/:picture_id/:item_id',
                            'defaults' => [
                                'controller' => Controller\Api\PictureItemController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'item' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'item'
                                    ]
                                ]
                            ],
                            'create' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb'     => 'post',
                                    'defaults' => [
                                        'action' => 'create'
                                    ]
                                ]
                            ],
                            'update' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb'     => 'put',
                                    'defaults' => [
                                        'action' => 'update'
                                    ]
                                ]
                            ],
                            'delete' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb'     => 'delete',
                                    'defaults' => [
                                        'action' => 'delete'
                                    ]
                                ]
                            ],
                        ]
                    ],
                    'traffic' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/traffic',
                            'defaults' => [
                                'controller' => Controller\Api\TrafficController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'list'
                                    ]
                                ]
                            ],
                            'whitelist' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/whitelist'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'list' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'get',
                                            'defaults' => [
                                                'action' => 'whitelist-list'
                                            ]
                                        ]
                                    ],
                                    'create' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'whitelist-create'
                                            ]
                                        ]
                                    ],
                                    'item' => [
                                        'type' => Segment::class,
                                        'options' => [
                                            'route' => '/:ip'
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => Method::class,
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
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/blacklist'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'create' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'post',
                                            'defaults' => [
                                                'action' => 'blacklist-create'
                                            ]
                                        ]
                                    ],
                                    'item' => [
                                        'type' => Segment::class,
                                        'options' => [
                                            'route' => '/:ip'
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => Method::class,
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
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/user',
                            'defaults' => [
                                'controller' => Controller\Api\UserController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
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
                    ],
                    'spec' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/spec',
                            'defaults' => [
                                'controller' => Controller\Api\SpecController::class
                            ]
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'get' => [
                                'type' => Method::class,
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
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/stat',
                            'defaults' => [
                                'controller' => Controller\Api\StatController::class
                            ]
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'global-summary' => [
                                'type' => Literal::class,
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
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/vehicle-types',
                            'defaults' => [
                                'controller' => Controller\Api\VehicleTypesController::class,
                                'action'     => 'index'
                            ],
                        ]
                    ],
                    'perspective' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/perspective',
                            'defaults' => [
                                'controller' => Controller\Api\PerspectiveController::class,
                                'action'     => 'index'
                            ],
                        ]
                    ],
                    'perspective-page' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/perspective-page',
                            'defaults' => [
                                'controller' => Controller\Api\PerspectivePageController::class,
                                'action'     => 'index'
                            ],
                        ]
                    ],
                    'picture-moder-vote-template' => [
                        'type' => Literal::class,
                        'options' => [
                            'route'    => '/picture-moder-vote-template',
                            'defaults' => [
                                'controller' => Controller\Api\PictureModerVoteTemplateController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'list' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ],
                            'create' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb' => 'post',
                                    'defaults' => [
                                        'action' => 'create'
                                    ]
                                ]
                            ],
                            'item' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:id'
                                ],
                                'may_terminate' => false,
                                'child_routes' => [
                                    'delete' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'delete',
                                            'defaults' => [
                                                'action' => 'delete'
                                            ]
                                        ]
                                    ],
                                    'get' => [
                                        'type' => Method::class,
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
