<?php

namespace Application;

use Zend\Form\ElementFactory;
use Zend\Validator as ZendValidator;

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
        'LoginForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'login/sign-in',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'login',
                        'options' => [
                            'label'        => 'login/login-or-email',
                            'maxlength'    => 50,
                            'autocomplete' => 'email',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'password',
                        'name' => 'password',
                        'options' => [
                            'label' => 'user/password'
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Checkbox',
                        'name' => 'remember',
                        'options' => [
                            'label' => 'login/remember'
                        ]
                    ]
                ]
            ],
            'input_filter' => [
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
                    'required' => false
                ]
            ]
        ],
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
        'AccountProfileForm' => [
            'type'        => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => Form\Element\UserName::class,
                        'name' => 'name',
                    ]
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required' => true,
                ]
            ]
        ],
        'AccountSettingsForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'language',
                        'options' => [
                            'label' => 'account/profile/language'
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Select',
                        'name' => 'timezone',
                        'options' => [
                            'label' => 'account/profile/timezone'
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'language' => [
                    'required' => true
                ],
                'timezone' => [
                    'required' => true
                ]
            ]
        ],
        'AccountPhotoForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method'  => 'post',
                'enctype' => 'multipart/form-data',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'File',
                        'name' => 'photo',
                        'options' => [
                            'label' => 'account/profile/photo'
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'photo' => [
                    'required' => true,
                    'validators' => [
                        /*[
                            'name' => ZendValidator\File\Count::class,
                            'break_chain_on_failure' => true,
                            'options' => [
                                'min' => 1,
                                'max' => 1
                            ]
                        ],*/
                        [
                            'name' => ZendValidator\File\Size::class,
                            'break_chain_on_failure' => true,
                            'options' => [
                                'max' => 4194304
                            ]
                        ],
                        [
                            'name' => ZendValidator\File\IsImage::class,
                            'break_chain_on_failure' => true,
                        ],
                        [
                            'name' => ZendValidator\File\Extension::class,
                            'break_chain_on_failure' => true,
                            'options' => [
                                'extension' => 'jpg,jpeg,jpe,png,gif,bmp'
                            ]
                        ],
                        [
                            'name' => ZendValidator\File\ImageSize::class,
                            'break_chain_on_failure' => true,
                            'options' => [
                                'minWidth'  => 100,
                                'minHeight' => 100
                            ]
                        ],

                    ]
                ]
            ]
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
