<?php

namespace Application;

use Zend\Form\ElementFactory;

return [
    'form_elements' => [
        'aliases' => [
            'year' => Form\Element\Year::class,
            'Year' => Form\Element\Year::class,
        ],
        'factories' => [
            Form\Element\Year::class         => ElementFactory::class,
        ]
    ],
    'forms' => [
        'AttrsLogFilterForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'user_id',
                        'options' => [
                            'label' => 'specifications-editor/log/filter/user-id'
                        ]
                    ]
                ]
            ]
        ],
    ]
];
