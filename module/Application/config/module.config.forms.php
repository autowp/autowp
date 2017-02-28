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
        'FeedbackForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'feedback/title',
            ],
            'elements' => [
                'name' => [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'name',
                        'options' => [
                            'label' => 'feedback/name',
                        ],
                        'attributes' => [
                            'maxlength'    => 255,
                            'size'         => 80,
                            'autocomplete' => 'name',
                        ]
                    ],
                ],
                'email' => [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'email',
                        'options' => [
                            'label' => 'E-mail',
                        ],
                        'attributes' => [
                            'maxlength'    => 255,
                            'size'         => 80,
                            'autocomplete' => 'email',
                        ]
                    ],
                ],
                'message' => [
                    'spec' => [
                        'type' => 'Textarea',
                        'name' => 'message',
                        'options' => [
                            'label' => 'feedback/message',
                        ],
                        'attributes' => [
                            'cols' => 80,
                            'rows' => 8,
                        ]
                    ],
                ],
                'captcha' => [
                    'spec' => [
                        'type' => 'Captcha',
                        'name' => 'captcha',
                        'options' => [
                            'label' => 'login/captcha',
                            'captcha' => [
                                'class'   => 'Image',
                                'font'    => __DIR__ . '/../assets/fonts/arial.ttf',
                                'imgDir'  => __DIR__ . '/../../../public_html/img/captcha/',
                                'imgUrl'  => '/img/captcha/',
                                'wordLen' => 4,
                                'timeout' => 300,
                            ]
                        ],
                    ],
                ],
                'submit' => [
                    'spec' => [
                        'type' => 'Submit',
                        'name' => 'submit',
                        'attributes' => [
                            'value' => 'Send',
                        ]
                    ],
                ],
            ],
            'input_filter' => [
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
        ],
        'RestorePasswordForm' => [
            //'hydrator' => 'ObjectProperty',
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
                            'label' => 'E-mail',
                        ],
                        'attributes' => [
                            'maxlength'    => 255,
                            'size'         => 80,
                            'autocomplete' => 'email',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Submit',
                        'name' => 'submit',
                        'attributes' => [
                            'value' => 'Send',
                        ]
                    ],
                ],
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
                        ['name' => Validator\User\EmailExists::class]
                    ]
                ],
            ],
        ],
        'NewPasswordForm' => [
            //'hydrator' => 'ObjectProperty',
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
                ],
                [
                    'spec' => [
                        'type' => Form\Element\UserPassword::class,
                        'name' => 'password_confirm',
                        'options' => [
                            'label' => 'user/password-confirm',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Submit',
                        'name' => 'submit',
                        'attributes' => [
                            'value' => 'Send',
                        ]
                    ],
                ],
            ],
            'input_filter' => [
                'password' => [
                    'required' => true
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
        'DescriptionForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Textarea',
                        'name' => 'markdown',
                        'attributes' => [
                            'maxlength' => 4096,
                            'cols'      => 60,
                            'rows'      => 10
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'markdown' => [
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
        'RegistrationForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                'email' => [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'email',
                        'options' => [
                            'label'     => 'E-mail',
                            'size'      => 20,
                            'maxlength' => 50,
                        ]
                    ]
                ],
                'name' => [
                    'spec' => [
                        'type' => Form\Element\UserName::class,
                        'name' => 'name'
                    ]
                ],
                'password' => [
                    'spec' => [
                        'type' => Form\Element\UserPassword::class,
                        'name' => 'password',
                    ]
                ],
                'password_confirm' => [
                    'spec' => [
                        'type' => Form\Element\UserPassword::class,
                        'name' => 'password_confirm',
                        'options' => [
                            'label' => 'user/password-confirm',
                        ]
                    ]
                ],
                'captcha' => [
                    'spec' => [
                        'type' => 'Captcha',
                        'name' => 'captcha',
                        'options' => [
                            'label' => 'login/captcha',
                            'captcha' => [
                                'class'   => 'Image',
                                'font'    => __DIR__ . '/../assets/fonts/arial.ttf',
                                'imgDir'  => __DIR__ . '/../../../public_html/img/captcha/',
                                'imgUrl'  => '/img/captcha/',
                                'wordLen' => 4,
                                'timeout' => 300,
                            ]
                        ],
                    ],
                ]
            ],
            'input_filter' => [
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
                    'required' => true
                ],
                'password' => [
                    'required' => true
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
                ]
            ]
        ],
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
        'BrandLogoForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method'  => 'post',
                'enctype' => 'multipart/form-data',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'File',
                        'name' => 'logo',
                        'options' => [
                            'label' => 'brand/logo'
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'logo' => [
                    'required' => true,
                    'validators' => [
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
                                'extension' => 'png'
                            ]
                        ],
                        [
                            'name' => ZendValidator\File\ImageSize::class,
                            'break_chain_on_failure' => true,
                            'options' => [
                                'minWidth'  => 50,
                                'minHeight' => 50
                            ]
                        ],

                    ]
                ]
            ]
        ],
        'BanForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'period',
                        'options' => [
                            'label'   => 'ban/period',
                            'options' => [
                                1  => 'ban/period/hour',
                                2  => 'ban/period/2-hours',
                                4  => 'ban/period/4-hours',
                                8  => 'ban/period/8-hours',
                                16 => 'ban/period/16-hours',
                                24 => 'ban/period/day',
                                48 => 'ban/period/2-days',
                            ]
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type'    => 'Text',
                        'name'    => 'reason',
                        'options' => [
                            'label' => 'ban/reason'
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type'    => 'Submit',
                        'name'    => 'submit',
                        'options' => [
                            'label' => 'ban/ban',
                        ]
                    ]
                ]
            ],
            'input_filter' => [
                'period' => [
                    'required' => true
                ],
                'reason' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'submit' => [
                    'required' => false
                ]
            ]
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
        'PictureModerVoteTemplateForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/picture/acceptance/add-reason',
            ],
            'elements' => [
                'reason' => [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'reason',
                        'options' => [
                            'label' => 'moder/picture/acceptance/reason'
                        ],
                        'attributes' => [
                            'maxlength'    => 50,
                            'size'         => 50
                        ]
                    ],
                ],
                'vote' => [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'vote',
                        'options' => [
                            'label'   => 'moder/picture/acceptance/vote',
                            'options' => [
                                -1 => 'Negative',
                                 1 => 'Positive'
                            ]
                        ]
                    ]
                ],
            ],
            'input_filter' => [
                'reason' => [
                    'required'   => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'vote' => [
                    'required'   => true
                ]
            ],
        ],
    ]
];
