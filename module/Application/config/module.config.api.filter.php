<?php

namespace Application;

use Autowp\Comments\CommentsService;
use Autowp\Message\MessageService;
use Autowp\ZFComponents\Filter\SingleSpaces;
use Autowp\User\Model\User;
use Zend\InputFilter\InputFilter;
use Autowp\Forums\Forums;

return [
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
        'api_attr_conflict_get' => [
            'filter' => [
                'required' => true,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                '0',
                                '1',
                                '-1',
                                'minus-weight'
                            ]
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
                        'options' => ['fields' => ['values']]
                    ]
                ]
            ],
        ],
        'api_attr_user_value_get' => [
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
                        'options' => ['fields' => ['user', 'item']]
                    ]
                ]
            ],
        ],
        'api_comments_get' => [
            'user_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'user' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'moderator_attention' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                \Autowp\Comments\Attention::NONE,
                                \Autowp\Comments\Attention::REQUIRED,
                                \Autowp\Comments\Attention::COMPLETED
                            ]
                        ]
                    ]
                ]
            ],
            'pictures_of_item_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'item_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'type_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'parent_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'no_parents' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['preview', 'text_html', 'user', 'url', 'replies', 'datetime', 'vote', 'user_vote', 'is_new', 'status']]
                    ]
                ]
            ],
            'order' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'date_desc',
                                'date_asc',
                                'vote_desc',
                                'vote_asc'
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
        ],
        'api_comments_get_public' => [
            'user_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'moderator_attention' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                \Autowp\Comments\Attention::NONE,
                                \Autowp\Comments\Attention::REQUIRED,
                                \Autowp\Comments\Attention::COMPLETED
                            ]
                        ]
                    ]
                ]
            ],
            'pictures_of_item_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'item_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'type_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'parent_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'no_parents' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits']
                ]
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['text_html', 'user', 'url', 'replies', 'datetime', 'vote', 'user_vote', 'is_new']]
                    ]
                ]
            ],
            'order' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'date_asc'
                            ]
                        ]
                    ]
                ]
            ],
            'order' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'date_desc',
                                'date_asc',
                                'vote_desc',
                                'vote_asc'
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
        ],
        'api_comments_post' => [
            'item_id' => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'Digits']
                ],
            ],
            'type_id' => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'Digits']
                ],
            ],
            'message' => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => CommentsService::MAX_MESSAGE_LENGTH
                        ]
                    ]
                ]
            ],
            'moderator_attention' => [
                'required'   => false,
                'filters'  => [
                    ['name' => 'Digits']
                ],
            ],
            'parent_id' => [
                'required'   => false,
                'filters'  => [
                    ['name' => 'Digits']
                ],
            ],
            'resolve' => [
                'required'   => false,
                'filters'  => [
                    ['name' => 'Digits']
                ],
            ]
        ],
        'api_comments_put' => [
            'user_vote' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                '-1',
                                '1'
                            ]
                        ]
                    ]
                ]
            ],
            'deleted' => [
                'required'   => false,
                'filters'  => [
                    ['name' => 'Digits']
                ],
            ],
            'item_id' => [
                'required'   => false,
                'filters'  => [
                    ['name' => 'Digits']
                ],
            ]
        ],
        'api_comments_item_get' => [
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
                        'options' => ['fields' => ['preview', 'text_html', 'user', 'url', 'replies', 'datetime', 'vote', 'user_vote', 'is_new', 'status', 'page']]
                    ]
                ]
            ],
        ],
        'api_contacts_list' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['avatar', 'gravatar']]
                    ]
                ]
            ],
        ],
        'api_feedback' => [
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
        'api_forum_theme_list' => [
            'theme_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['description', 'themes', 'last_topic', 'last_message', 'topics']]
                    ]
                ]
            ],
        ],
        'api_forum_theme_get' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['description', 'themes', 'last_topic', 'last_message', 'topics']]
                    ]
                ]
            ],
            'topics' => [
                'type' => InputFilter::class,
                'page' => [
                    'required' => false,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        ['name' => 'Digits']
                    ]
                ]
            ]
        ],
        'api_forum_topic_list' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['last_message', 'author', 'messages', 'theme', 'subscription']]
                    ]
                ]
            ],
            'subscription' => [
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
            'theme_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
        ],
        'api_forum_topic_get' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['last_message', 'author', 'messages', 'theme', 'subscription']]
                    ]
                ]
            ]
        ],
        'api_forum_topic_post' => [
            'theme_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'name' => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => 100
                        ]
                    ]
                ]
            ],
            'text' => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => 1024 * 4
                        ]
                    ]
                ]
            ],
            'moderator_attention' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'subscription' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ]
        ],
        'api_forum_topic_put' => [
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
                                Forums::STATUS_NORMAL,
                                Forums::STATUS_CLOSED,
                                Forums::STATUS_DELETED
                            ]
                        ]
                    ]
                ]
            ],
            'subscription' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'theme_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
        ],
        'api_inbox_get' => [
            'brand_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => 'Digits']
                ]
            ],
            'date' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
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
        'api_login' => [
            'login' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => null,
                            'max' => 50
                        ]
                    ],
                    ['name' => Validator\User\Login::class]
                ]
            ],
            'password' => [
                'required' => true
            ],
            'remember' => [
                'required'    => false,
                'allow_empty' => true
            ]
        ],
        'api_message_list' => [
            'user_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'folder' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                'inbox',
                                'sent',
                                'system',
                                'dialog'
                            ]
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
                        'options' => ['fields' => ['author']]
                    ]
                ]
            ]
        ],
        'api_message_post' => [
            'user_id' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'text' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => MessageService::MAX_TEXT
                        ]
                    ]
                ]
            ]
        ],
        'api_new_get' => [
            'date' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
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
                        'options' => ['fields' => ['pictures', 'item', 'item_pictures']]
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
            'exact_item_link_type' => [
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
                'required' => false,
                'filters' => [
                    ['name' => 'Digits']
                ],
            ],
            'similar' => [
                'required' => false
            ],
            'add_date' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name'    => 'Date',
                        'options' => [
                            'format' => 'Y-m-d'
                        ]
                    ]
                ]
            ],
            'accept_date' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name'    => 'Date',
                        'options' => [
                            'format' => 'Y-m-d'
                        ]
                    ]
                ]
            ],
        ],
        'api_picture_list_public' => [
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
                            'max' => 30
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
                            'owner', 'thumbnail', 'votes',
                            'comments_count', 'name_html', 'name_text'
                        ]]
                    ]
                ]
            ],
            'status' => [
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
            'owner_id' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'order' => [
                'required' => false,
                'filters' => [
                    ['name' => 'Digits']
                ],
            ],
            'add_date' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name'    => 'Date',
                        'options' => [
                            'format' => 'Y-m-d'
                        ]
                    ]
                ]
            ],
            'accept_date' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name'    => 'Date',
                        'options' => [
                            'format' => 'Y-m-d'
                        ]
                    ]
                ]
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
            'exact_item_link_type' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
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
        'api_restore_password_request' => [
            'email' => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name'                   => 'EmailAddress',
                        'break_chain_on_failure' => true
                    ],
                    ['name' => Validator\User\EmailExists::class]
                ]
            ],
        ],
        'api_restore_password_new' => [
            'code' => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => null,
                            'max' => 500
                        ]
                    ],
                ]
            ],
            'password' => [
                'required'   => true,
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => User::MIN_PASSWORD,
                            'max' => User::MAX_PASSWORD
                        ]
                    ]
                ]
            ],
            'password_confirm' => [
                'required'   => true,
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => User::MIN_PASSWORD,
                            'max' => User::MAX_PASSWORD
                        ]
                    ],
                    [
                        'name' => 'Identical',
                        'options' => [
                            'token' => 'password',
                        ],
                    ]
                ]
            ]
        ],
        'api_user_item' => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['last_online', 'reg_date', 'image', 'email', 'login', 'avatar', 'gravatar', 'timezone', 'language', 'votes_per_day',' votes_left', 'img', 'specs_weight', 'identity']]
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
            'identity' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ]
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name' => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['last_online', 'reg_date', 'image', 'email', 'login', 'avatar', 'gravatar', 'timezone', 'language', 'votes_per_day',' votes_left', 'img', 'specs_weight', 'identity']]
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
            ],
            'name' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => User::MIN_NAME,
                            'max' => User::MAX_NAME
                        ]
                    ]
                ]
            ],
            'language' => [
                'required' => true,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => []
                        ]
                    ]
                ]
            ],
            'timezone' => [
                'required' => true,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => []
                        ]
                    ]
                ]
            ],
            'email' => [
                'required'   => true,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name'                   => 'EmailAddress',
                        'break_chain_on_failure' => true
                    ],
                    ['name' => Validator\User\EmailNotExists::class]
                ]
            ],
            'password_old' => [
                'required' => true,
            ],
            'password' => [
                'required' => true,
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => User::MIN_PASSWORD,
                            'max' => User::MAX_PASSWORD
                        ]
                    ]
                ]
            ],
            'password_confirm' => [
                'required'   => true,
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => User::MIN_PASSWORD,
                            'max' => User::MAX_PASSWORD
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
        'api_user_post' => [
            'email' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => null,
                            'max' => 50
                        ]
                    ],
                    [
                        'name'                   => 'EmailAddress',
                        'break_chain_on_failure' => true
                    ],
                    ['name' => Validator\User\EmailNotExists::class]
                ]
            ],
            'name' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => User::MIN_NAME,
                            'max' => User::MAX_NAME
                        ]
                    ]
                ]
            ],
            'password' => [
                'required'   => true,
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => User::MIN_PASSWORD,
                            'max' => User::MAX_PASSWORD
                        ]
                    ]
                ]
            ],
            'password_confirm' => [
                'required'   => true,
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => User::MIN_PASSWORD,
                            'max' => User::MAX_PASSWORD
                        ]
                    ],
                    [
                        'name' => 'Identical',
                        'options' => [
                            'token' => 'password',
                        ],
                    ]
                ]
            ]
        ],
        'api_user_photo_post' => [
            'file' => [
                'required' => true,
                'validators' => [
                    /*[
                     'name' => 'FileCount',
                     'break_chain_on_failure' => true,
                     'options' => [
                     'min' => 1,
                     'max' => 1
                     ]
                     ],*/
                    [
                        'name' => 'FileSize',
                        'break_chain_on_failure' => true,
                        'options' => [
                            'max' => 4194304
                        ]
                    ],
                    [
                        'name' => 'FileIsImage',
                        'break_chain_on_failure' => true,
                    ],
                    [
                        'name' => 'FileExtension',
                        'break_chain_on_failure' => true,
                        'options' => [
                            'extension' => 'jpg,jpeg,jpe,png,gif,bmp'
                        ]
                    ],
                    [
                        'name' => 'FileImageSize',
                        'break_chain_on_failure' => true,
                        'options' => [
                            'minWidth'  => 100,
                            'minHeight' => 100
                        ]
                    ],
                ]
            ]
        ]
    ]
];