<?php

declare(strict_types=1);

namespace Application;

use Autowp\Comments\CommentsService;
use Laminas\InputFilter\InputFilter;

return [
    'input_filter_specs' => [
        'api_contacts_list'                    => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['avatar', 'gravatar']],
                    ],
                ],
            ],
        ],
        'api_feedback'                         => [
            'name'    => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'email'   => [
                'required'   => true,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'EmailAddress'],
                ],
            ],
            'message' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
        ],
        'api_inbox_get'                        => [
            'brand_id' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => 'Digits'],
                ],
            ],
            'date'     => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => Validator\DateString::class,
                        'options' => [
                            'format' => 'Y-m-d',
                        ],
                    ],
                ],
            ],
            'page'     => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true,
                        ],
                    ],
                ],
            ],
        ],
        'api_ip_item'                          => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['hostname', 'rights', 'blacklist']],
                    ],
                ],
            ],
        ],
        'api_item_list'                        => [
            'descendant'              => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'ancestor_id'             => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'parent_id'               => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'type_id'                 => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'InArray',
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
                                Model\Item::COPYRIGHT,
                            ],
                        ],
                    ],
                ],
            ],
            'concept'                 => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'concept_inherit'         => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'vehicle_type_id'         => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'vehicle_childs_type_id'  => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'spec'                    => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'limit'                   => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'], // Order matters in ItemController
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1,
                            'max' => 500,
                        ],
                    ],
                ],
            ],
            'page'                    => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true,
                        ],
                    ],
                ],
            ],
            'fields'                  => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => [
                            'fields' => [
                                'childs_count',
                                'childs_counts',
                                'name_html',
                                'name_text',
                                'name_default',
                                'name_only',
                                'other_names',
                                'description',
                                'text',
                                'has_text',
                                'brands',
                                'spec_editor_url',
                                'specs_url',
                                'categories',
                                'twins_groups',
                                'url',
                                'preview_pictures',
                                'design',
                                'engine_vehicles',
                                'catname',
                                'is_concept',
                                'spec_id',
                                'begin_year',
                                'end_year',
                                'body',
                                'lat',
                                'lng',
                                'pictures_count',
                                'current_pictures_count',
                                'item_of_day_pictures',
                                'related_group_pictures',
                                'engine_id',
                                'attr_zone_id',
                                'descendants_count',
                                'has_child_specs',
                                'accepted_pictures_count',
                                'inbox_pictures_count',
                                'comments_topic_stat',
                                'front_picture',
                                'has_specs',
                                'alt_names',
                                'descendant_twins_groups_count',
                                'comments_attentions_count',
                                'mosts_active',
                                'exact_picture',
                            ],
                        ],
                    ],
                ],
            ],
            'order'                   => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => [
                                'name',
                                'childs_count',
                                'id_desc',
                                'id_asc',
                                'age',
                                'name_nat',
                                'categories_first',
                            ],
                        ],
                    ],
                ],
            ],
            'name_exclude'            => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'no_parent'               => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'text'                    => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'from_year'               => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'to_year'                 => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'factories_of_brand'      => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'have_common_childs_with' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'descendant_pictures'     => [
                'type'                    => InputFilter::class,
                'status'                  => [
                    'required' => false,
                ],
                'type_id'                 => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        [
                            'name'    => 'InArray',
                            'options' => [
                                'haystack' => [
                                    Model\PictureItem::PICTURE_AUTHOR,
                                    Model\PictureItem::PICTURE_CONTENT,
                                    Model\PictureItem::PICTURE_COPYRIGHTS,
                                ],
                            ],
                        ],
                    ],
                ],
                'owner_id'                => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'perspective_id'          => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'contains_perspective_id' => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
            ],
            'preview_pictures'        => [
                'type'                    => InputFilter::class,
                'type_id'                 => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        [
                            'name'    => 'InArray',
                            'options' => [
                                'haystack' => [
                                    Model\PictureItem::PICTURE_AUTHOR,
                                    Model\PictureItem::PICTURE_CONTENT,
                                    Model\PictureItem::PICTURE_COPYRIGHTS,
                                ],
                            ],
                        ],
                    ],
                ],
                'perspective_id'          => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'contains_perspective_id' => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
            ],
            'related_groups_of'       => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'dateless'                => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'dateful'                 => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'route_brand_id'          => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
        ],
        'api_item_item'                        => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => [
                            'fields' => [
                                'childs_count',
                                'childs_counts',
                                'name_html',
                                'name_text',
                                'name_default',
                                'name_only',
                                'other_names',
                                'description',
                                'text',
                                'has_text',
                                'brands',
                                'spec_editor_url',
                                'specs_route',
                                'categories',
                                'twins_groups',
                                'url',
                                'preview_pictures',
                                'design',
                                'engine_vehicles',
                                'catname',
                                'is_concept',
                                'spec_id',
                                'begin_year',
                                'end_year',
                                'body',
                                'lat',
                                'lng',
                                'pictures_count',
                                'current_pictures_count',
                                'item_of_day_pictures',
                                'related_group_pictures',
                                'engine_id',
                                'attr_zone_id',
                                'descendants_count',
                                'has_child_specs',
                                'accepted_pictures_count',
                                'inbox_pictures_count',
                                'comments_topic_stat',
                                'front_picture',
                                'has_specs',
                                'alt_names',
                                'descendant_twins_groups_count',
                                'comments_attentions_count',
                                'mosts_active',
                                'exact_picture',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'api_item_logo_put'                    => [
            'file' => [
                'required'   => true,
                'validators' => [
                    [
                        'name'                   => 'FileSize',
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'max' => 10 * 1024 * 1024,
                        ],
                    ],
                    [
                        'name'                   => 'FileIsImage',
                        'break_chain_on_failure' => true,
                    ],
                    [
                        'name'                   => 'FileMimeType',
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'mimeType' => 'image/png',
                        ],
                    ],
                    [
                        'name'                   => 'FileImageSize',
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'minWidth'  => 50,
                            'minHeight' => 50,
                        ],
                    ],
                ],
            ],
        ],
        'api_item_parent_list'                 => [
            'concept'         => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'concept_inherit' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'exclude_concept' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'type_id'         => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'item_type_id'    => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'item_id'         => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'parent_id'       => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'catname'         => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'limit'           => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1,
                            'max' => 500,
                        ],
                    ],
                ],
            ],
            'page'            => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true,
                        ],
                    ],
                ],
            ],
            'fields'          => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['item']],
                    ],
                ],
            ],
            'is_group'        => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'order'           => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => [
                                'name',
                                'childs_count',
                                'type_auto',
                                'categories_first',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'api_item_parent_item'                 => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['item']],
                    ],
                ],
            ],
        ],
        'api_message_list'                     => [
            'user_id' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'folder'  => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => [
                                'inbox',
                                'sent',
                                'system',
                                'dialog',
                            ],
                        ],
                    ],
                ],
            ],
            'page'    => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true,
                        ],
                    ],
                ],
            ],
            'fields'  => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['author']],
                    ],
                ],
            ],
        ],
        'api_new_get'                          => [
            'date'   => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => Validator\DateString::class,
                        'options' => [
                            'format' => 'Y-m-d',
                        ],
                    ],
                ],
            ],
            'page'   => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true,
                        ],
                    ],
                ],
            ],
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['pictures', 'item', 'item_pictures']],
                    ],
                ],
            ],
        ],
        'api_perspective_page_list'            => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => ['fields' => ['groups']],
                    ],
                ],
            ],
        ],
        'api_picture_list'                     => [
            'identity'               => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 1,
                            'max' => 20,
                        ],
                    ],
                ],
            ],
            'limit'                  => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 0,
                            'max' => 500,
                        ],
                    ],
                ],
            ],
            'page'                   => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true,
                        ],
                    ],
                ],
            ],
            'fields'                 => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => [
                            'fields' => [
                                'owner',
                                'thumbnail',
                                'moder_vote',
                                'votes',
                                'similar',
                                'comments_count',
                                'add_date',
                                'iptc',
                                'exif',
                                'image',
                                'items',
                                'special_name',
                                'copyrights',
                                'change_status_user',
                                'rights',
                                'moder_votes',
                                'moder_voted',
                                'is_last',
                                'accepted_count',
                                'crop',
                                'replaceable',
                                'perspective_item',
                                'siblings',
                                'ip',
                                'name_html',
                                'name_text',
                                'image_gallery_full',
                                'preview_large',
                                'dpi',
                                'point',
                                'authors',
                                'categories',
                                'twins',
                                'factories',
                                'of_links',
                                'copyright_blocks',
                                'path',
                            ],
                        ],
                    ],
                ],
            ],
            'status'                 => [
                'required' => false,
            ],
            'car_type_id'            => [
                'required' => false,
            ],
            'perspective_id'         => [
                'required' => false,
            ],
            'perspective_exclude_id' => [
                'required' => false,
            ],
            'exact_item_id'          => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'exact_item_link_type'   => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'item_id'                => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'exclude_item_id'        => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'type_id'                => [
                'required' => false,
            ],
            'comments'               => [
                'required' => false,
            ],
            'owner_id'               => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'replace'                => [
                'required' => false,
            ],
            'requests'               => [
                'required' => false,
            ],
            'special_name'           => [
                'required' => false,
            ],
            'lost'                   => [
                'required' => false,
            ],
            'gps'                    => [
                'required' => false,
            ],
            'order'                  => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits'],
                ],
            ],
            'similar'                => [
                'required' => false,
            ],
            'add_date'               => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => Validator\DateString::class,
                        'options' => [
                            'format' => 'Y-m-d',
                        ],
                    ],
                ],
            ],
            'added_from'             => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'Date',
                        'options' => [
                            'format' => 'Y-m-d',
                        ],
                    ],
                ],
            ],
            'paginator'              => [
                'type'                   => InputFilter::class,
                'item_id'                => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'exact_item_link_type'   => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'exact_item_id'          => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'perspective_id'         => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'perspective_exclude_id' => [
                    'required' => false,
                ],
            ],
        ],
        'api_picture_list_public'              => [
            'identity'               => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 1,
                            'max' => 20,
                        ],
                    ],
                ],
            ],
            'limit'                  => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 0,
                            'max' => 32,
                        ],
                    ],
                ],
            ],
            'page'                   => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'GreaterThan',
                        'options' => [
                            'min'       => 1,
                            'inclusive' => true,
                        ],
                    ],
                ],
            ],
            'fields'                 => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => [
                            'fields' => [
                                'owner',
                                'thumbnail',
                                'votes',
                                'comments_count',
                                'name_html',
                                'name_text',
                                'image_gallery_full',
                                'preview_large',
                                'dpi',
                                'point',
                                'authors',
                                'categories',
                                'twins',
                                'factories',
                                'of_links',
                                'copyright_blocks',
                                'path',
                            ],
                        ],
                    ],
                ],
            ],
            'status'                 => [
                'required' => false,
            ],
            'item_id'                => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'owner_id'               => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'order'                  => [
                'required' => false,
                'filters'  => [
                    ['name' => 'Digits'],
                ],
            ],
            'add_date'               => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => Validator\DateString::class,
                        'options' => [
                            'format' => 'Y-m-d',
                        ],
                    ],
                ],
            ],
            'perspective_id'         => [
                'required' => false,
            ],
            'perspective_exclude_id' => [
                'required' => false,
            ],
            'exact_item_id'          => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'exact_item_link_type'   => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'paginator'              => [
                'type'                   => InputFilter::class,
                'item_id'                => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'exact_item_link_type'   => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'exact_item_id'          => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'perspective_id'         => [
                    'required'   => false,
                    'filters'    => [
                        ['name' => 'StringTrim'],
                    ],
                    'validators' => [
                        ['name' => 'Digits'],
                    ],
                ],
                'perspective_exclude_id' => [
                    'required' => false,
                ],
            ],
        ],
        'api_picture_post'                     => [
            'file'               => [
                'required'   => true,
                'validators' => [
                    [
                        'name'    => 'FileSize',
                        'options' => [
                            'max'           => 1024 * 1024 * 100,
                            'useByteString' => false,
                        ],
                    ],
                    ['name' => 'FileIsImage'],
                    [
                        'name'    => 'FileExtension',
                        'options' => [
                            'extension' => 'jpg,jpeg,jpe,png',
                        ],
                    ],
                    [
                        'name'    => 'FileImageSize',
                        'options' => [
                            'minWidth'  => 640,
                            'minHeight' => 360,
                            'maxWidth'  => 10000,
                            'maxHeight' => 10000,
                        ],
                    ],
                ],
            ],
            'comment'            => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => CommentsService::MAX_MESSAGE_LENGTH,
                        ],
                    ],
                ],
            ],
            'item_id'            => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'replace_picture_id' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'perspective_id'     => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
        ],
        'api_picture_item'                     => [
            'fields' => [
                'required' => false,
                'filters'  => [
                    [
                        'name'    => Filter\Api\FieldsFilter::class,
                        'options' => [
                            'fields' => [
                                'owner',
                                'thumbnail',
                                'moder_vote',
                                'votes',
                                'similar',
                                'comments_count',
                                'add_date',
                                'iptc',
                                'exif',
                                'image',
                                'items',
                                'special_name',
                                'copyrights',
                                'change_status_user',
                                'rights',
                                'moder_votes',
                                'moder_voted',
                                'is_last',
                                'accepted_count',
                                'crop',
                                'replaceable',
                                'perspective_item',
                                'siblings',
                                'ip',
                                'name_html',
                                'name_text',
                                'image_gallery_full',
                                'preview_large',
                                'dpi',
                                'point',
                                'authors',
                                'categories',
                                'twins',
                                'factories',
                                'of_links',
                                'copyright_blocks',
                                'path',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'api_picture_moder_vote_template_list' => [
            'name' => [
                'required'   => true,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 1,
                            'max' => Model\PictureModerVote::MAX_LENGTH,
                        ],
                    ],
                ],
            ],
            'vote' => [
                'required'   => true,
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => [-1, 1],
                        ],
                    ],
                ],
            ],
        ],
        'api_user_put'                         => [
            'language' => [
                'required'   => true,
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => [],
                        ],
                    ],
                ],
            ],
            'timezone' => [
                'required'   => true,
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => [],
                        ],
                    ],
                ],
            ],
        ],
        'api_user_photo_post'                  => [
            'file' => [
                'required'   => true,
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
                        'name'                   => 'FileSize',
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'max' => 4194304,
                        ],
                    ],
                    [
                        'name'                   => 'FileIsImage',
                        'break_chain_on_failure' => true,
                    ],
                    [
                        'name'                   => 'FileExtension',
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'extension' => 'jpg,jpeg,jpe,png,gif,bmp',
                        ],
                    ],
                    [
                        'name'                   => 'FileImageSize',
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'minWidth'  => 100,
                            'minHeight' => 100,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
