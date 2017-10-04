<?php

namespace Application;

use Zend\Form\ElementFactory;

return [
    'form_elements' => [
        'aliases' => [
            'itemfullname' => Form\Element\ItemFullName::class,
            'itemFullName' => Form\Element\ItemFullName::class,
            'ItemFullName' => Form\Element\ItemFullName::class,
            'itembody' => Form\Element\ItemBody::class,
            'itemBody' => Form\Element\ItemBody::class,
            'ItemBody' => Form\Element\ItemBody::class,
            'itemname' => Form\Element\ItemName::class,
            'itemName' => Form\Element\ItemName::class,
            'ItemName' => Form\Element\ItemName::class,
            'year' => Form\Element\Year::class,
            'Year' => Form\Element\Year::class,
            'userpassword' => Form\Element\UserPassword::class,
            'userPassword' => Form\Element\UserPassword::class,
            'UserPassword' => Form\Element\UserPassword::class,
        ],
        'factories' => [
            Form\Element\ItemFullName::class => ElementFactory::class,
            Form\Element\ItemBody::class     => ElementFactory::class,
            Form\Element\ItemName::class     => ElementFactory::class,
            Form\Element\Year::class         => ElementFactory::class,
            Form\Element\UserPassword::class => ElementFactory::class,
        ]
    ],
    'forms' => [
        'AccountEmailForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'email',
                        'options' => [
                            'label'        => 'E-mail',
                            'maxlength'    => 255,
                            'size'         => 80,
                            'autocomplete' => 'email',
                        ]
                    ],
                ]
            ],
            'input_filter' => [
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
            ],
        ],
        'ChangePasswordForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => Form\Element\UserPassword::class,
                        'name'    => 'password_old',
                        'options' => [
                            'label' => 'account/access/change-password/current',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => Form\Element\UserPassword::class,
                        'name'    => 'password',
                        'options' => [
                            'label' => 'account/access/change-password/new',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type'    => Form\Element\UserPassword::class,
                        'name'    => 'password_confirm',
                        'options' => [
                            'label' => 'account/access/change-password/new-confirm'
                        ]
                    ],
                ]
            ],
            'input_filter' => [
                'password_old' => [
                    'required' => true,
                ],
                'password' => [
                    'required' => true,
                ],
                'password_confirm' => [
                    'required'   => true,
                    'validators' => [
                        [
                            'name' => 'Identical',
                            'options' => [
                                'token' => 'password',
                            ],
                        ]
                    ]
                ],
            ],
        ],
        'DeleteUserForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => Form\Element\UserPassword::class,
                        'name' => 'password'
                    ],
                ]
            ],
            'input_filter' => [
                'password' => [
                    'required' => true
                ]
            ],
        ],
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
