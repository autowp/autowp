<?php

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Method;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;

use Autowp\ZFComponents\Filter\SingleSpaces;

return [
    'hydrators' => [
        'factories' => [
            Hydrator\Api\CommentHydrator::class          => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\IpHydrator::class               => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemHydrator::class             => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemHydrator::class             => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemLanguageHydrator::class     => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemLinkHydrator::class         => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemParentHydrator::class       => Hydrator\Api\RestHydratorFactory::class,
            Hydrator\Api\ItemParentLanguageHydrator::class => Hydrator\Api\RestHydratorFactory::class,
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
            Controller\Api\ContactsController::class        => Controller\Api\ContactsControllerFactory::class,
            Controller\Api\ContentLanguageController::class => Controller\Api\ContentLanguageControllerFactory::class,
            Controller\Api\HotlinksController::class        => InvokableFactory::class,
            Controller\Api\IpController::class              => Controller\Api\Service\IpControllerFactory::class,
            Controller\Api\ItemController::class            => Controller\Api\Service\ItemControllerFactory::class,
            Controller\Api\ItemLanguageController::class    => Controller\Api\ItemLanguageControllerFactory::class,
            Controller\Api\ItemLinkController::class        => Controller\Api\ItemLinkControllerFactory::class,
            Controller\Api\ItemParentController::class      => Controller\Api\ItemParentControllerFactory::class,
            Controller\Api\ItemParentLanguageController::class => Controller\Api\ItemParentLanguageControllerFactory::class,
            Controller\Api\ItemVehicleTypeController::class => Controller\Api\Service\ItemVehicleTypeControllerFactory::class,
            Controller\Api\LogController::class             => Controller\Api\Service\LogControllerFactory::class,
            Controller\Api\PageController::class            => Controller\Api\Service\PageControllerFactory::class,
            Controller\Api\PerspectiveController::class     => Controller\Api\Service\PerspectiveControllerFactory::class,
            Controller\Api\PerspectivePageController::class => Controller\Api\Service\PerspectivePageControllerFactory::class,
            Controller\Api\PictureController::class         => Controller\Api\PictureControllerFactory::class,
            Controller\Api\PictureItemController::class     => Controller\Api\PictureItemControllerFactory::class,
            Controller\Api\PictureModerVoteController::class => Controller\Api\PictureModerVoteControllerFactory::class,
            Controller\Api\PictureModerVoteTemplateController::class => Controller\Api\Service\PictureModerVoteTemplateControllerFactory::class,
            Controller\Api\PictureVoteController::class     => Controller\Api\Service\PictureVoteControllerFactory::class,
            Controller\Api\SpecController::class            => Controller\Api\SpecControllerFactory::class,
            Controller\Api\StatController::class            => Controller\Api\StatControllerFactory::class,
            Controller\Api\TrafficController::class         => Controller\Api\Service\TrafficControllerFactory::class,
            Controller\Api\UserController::class            => Controller\Api\Service\UserControllerFactory::class,
            Controller\Api\VehicleTypesController::class    => Controller\Api\VehicleTypesControllerFactory::class
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
            'ancestor_id' => [
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
                                Model\Item::VEHICLE,
                                Model\Item::ENGINE,
                                Model\Item::CATEGORY,
                                Model\Item::TWINS,
                                Model\Item::BRAND,
                                Model\Item::FACTORY,
                                Model\Item::MUSEUM,
                                Model\Item::PERSON,
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
                            'has_text', 'brands', 'upload_url',
                            'spec_editor_url', 'specs_url', 'categories',
                            'twins_groups', 'url', 'more_pictures_url',
                            'preview_pictures', 'design', 'engine_vehicles',
                            'catname', 'is_concept', 'spec_id', 'begin_year',
                            'end_year', 'body', 'lat', 'lng']]
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
                                'id_asc',
                                'age'
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
            'is_group' => [
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
            ],
            'suggestions_to' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'engine_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'have_childs_of_type' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'have_common_childs_with' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'have_childs_with_parent_of_type' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'autocomplete' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'exclude_self_and_childs' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'parent_types_of' => [
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
                            'has_text', 'brands', 'upload_url',
                            'spec_editor_url', 'specs_url', 'categories',
                            'twins_groups', 'url', 'more_pictures_url',
                            'preview_pictures', 'design', 'engine_vehicles',
                            'catname', 'is_concept', 'spec_id', 'begin_year',
                            'end_year', 'body']]
                    ]
                ]
            ],
        ],
        'api_item_language_put' => [
            'name' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 255
                        ]
                    ]
                ]
            ],
            'text' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 4096
                        ]
                    ]
                ]
            ],
            'full_text' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 65536
                        ]
                    ]
                ]
            ],
        ],
        'api_item_link_index' => [
            'item_id' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
        ],
        'api_item_link_post' => [
            'item_id' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'name' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 255
                        ]
                    ]
                ]
            ],
            'url' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 255
                        ]
                    ]
                ]
            ],
            'type_id' => [
                'required'   => true,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'default',
                                'official',
                                'club'
                            ]
                        ]
                    ]
                ]
            ],
        ],
        'api_item_link_put' => [
            'name' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 255
                        ]
                    ]
                ]
            ],
            'url' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 255
                        ]
                    ]
                ]
            ],
            'type_id' => [
                'required'   => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'default',
                                'official',
                                'club'
                            ]
                        ]
                    ]
                ]
            ],
        ],
        'api_item_parent_language_put' => [
            'name' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Model\ItemParent::MAX_LANGUAGE_NAME
                        ]
                    ]
                ]
            ]
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
            'item_id' => [
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
            'is_group' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
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
                                'moder_auto',
                                'categories_first'
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
            'type_id' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                Model\ItemParent::TYPE_DEFAULT,
                                Model\ItemParent::TYPE_TUNING,
                                Model\ItemParent::TYPE_SPORT,
                                Model\ItemParent::TYPE_DESIGN
                            ]
                        ]
                    ]
                ]
            ],
            'catname' => [
                'required' => false,
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
                            'max' => Model\ItemParent::MAX_CATNAME
                        ]
                    ]
                ]
            ]
        ],
        'api_item_parent_put' => [
            'type_id' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                Model\ItemParent::TYPE_DEFAULT,
                                Model\ItemParent::TYPE_TUNING,
                                Model\ItemParent::TYPE_SPORT,
                                Model\ItemParent::TYPE_DESIGN
                            ]
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
                            'max' => Model\ItemParent::MAX_CATNAME
                        ]
                    ]
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
            'exact_item_id' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
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
                                Model\Picture::STATUS_INBOX,
                                Model\Picture::STATUS_ACCEPTED,
                                Model\Picture::STATUS_REMOVING,
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
                            'max' => Model\Picture::MAX_NAME,
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
        'api_picture_item_list' => [
            'item_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ]
            ],
            'picture_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ]
            ],
            'type' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                Model\PictureItem::PICTURE_CONTENT,
                                Model\PictureItem::PICTURE_AUTHOR
                            ]
                        ]
                    ]
                ]
            ],
            'order' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'status'
                            ]
                        ]
                    ]
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
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => [
                            'area', 'item', 'picture'
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
                            'area', 'item', 'picture'
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
                            'max' => Model\PictureModerVote::MAX_LENGTH
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
        'api_user_put' => [
            'deleted' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ]
        ]
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
                    'content-language' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/content-language',
                            'defaults' => [
                                'controller' => Controller\Api\ContentLanguageController::class
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
                        ]
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
                                    'put' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'put'
                                            ]
                                        ]
                                    ],
                                    'logo' => [
                                        'type' => Literal::class,
                                        'options' => [
                                            'route' => '/logo',
                                            'defaults' => [
                                                'action' => 'logo'
                                            ]
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'get' => [
                                                'type' => Method::class,
                                                'options' => [
                                                    'verb' => 'get',
                                                    'defaults' => [
                                                        'action' => 'get-logo'
                                                    ]
                                                ]
                                            ],
                                            'put' => [
                                                'type' => Method::class,
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
                                        'type' => Literal::class,
                                        'options' => [
                                            'route' => '/language',
                                            'defaults' => [
                                                'controller' => Controller\Api\ItemLanguageController::class
                                            ]
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
                                            'item' => [
                                                'type' => Segment::class,
                                                'options' => [
                                                    'route' => '/:language'
                                                ],
                                                'may_terminate' => false,
                                                'child_routes' => [
                                                    'get' => [
                                                        'type' => Method::class,
                                                        'options' => [
                                                            'verb' => 'get',
                                                            'defaults' => [
                                                                'action' => 'get'
                                                            ]
                                                        ]
                                                    ],
                                                    'put' => [
                                                        'type' => Method::class,
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
                                        'type' => Literal::class,
                                        'options' => [
                                            'route' => '/tree',
                                            'defaults' => [
                                                'action' => 'tree'
                                            ]
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'get' => [
                                                'type' => Method::class,
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
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/item-link',
                            'defaults' => [
                                'controller' => Controller\Api\ItemLinkController::class
                            ]
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
                                                'action' => 'get'
                                            ]
                                        ]
                                    ],
                                    'put' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'put'
                                            ]
                                        ]
                                    ],
                                    'delete' => [
                                        'type' => Method::class,
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
                                    'put' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb'     => 'put',
                                            'defaults' => [
                                                'action' => 'put'
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
                                    'language' => [
                                        'type' => Literal::class,
                                        'options' => [
                                            'route' => '/language',
                                            'defaults' => [
                                                'controller' => Controller\Api\ItemParentLanguageController::class
                                            ]
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
                                            'item' => [
                                                'type' => Segment::class,
                                                'options' => [
                                                    'route' => '/:language'
                                                ],
                                                'may_terminate' => false,
                                                'child_routes' => [
                                                    'get' => [
                                                        'type' => Method::class,
                                                        'options' => [
                                                            'verb' => 'get',
                                                            'defaults' => [
                                                                'action' => 'get'
                                                            ]
                                                        ]
                                                    ],
                                                    'put' => [
                                                        'type' => Method::class,
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
                                'type' => Method::class,
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
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/item-vehicle-type',
                            'defaults' => [
                                'controller' => Controller\Api\ItemVehicleTypeController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'index' => [
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
                                    'route' => '/:item_id/:vehicle_type_id',
                                    'defaults' => [
                                        'controller' => Controller\Api\ItemVehicleTypeController::class,
                                    ],
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
                            ]
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
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/picture-item',
                            'defaults' => [
                                'controller' => Controller\Api\PictureItemController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'get' => [
                                'type' => Method::class,
                                'options' => [
                                    'verb'     => 'get',
                                    'defaults' => [
                                        'action' => 'index'
                                    ]
                                ]
                            ] ,
                            'item' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:picture_id/:item_id/:type'
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
                            ]
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
                            ],
                            'user' => [
                                'type' => Segment::class,
                                'options' => [
                                    'route' => '/:id'
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
                                    'put' => [
                                        'type' => Method::class,
                                        'options' => [
                                            'verb' => 'put',
                                            'defaults' => [
                                                'action' => 'put'
                                            ]
                                        ]
                                    ],
                                    'photo' => [
                                        'type' => Literal::class,
                                        'options' => [
                                            'route'    => '/photo',
                                        ],
                                        'may_terminate' => false,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => Method::class,
                                                'options' => [
                                                    'verb' => 'delete',
                                                    'defaults' => [
                                                        'action' => 'delete-photo'
                                                    ]
                                                ]
                                            ],
                                        ]
                                    ]
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
