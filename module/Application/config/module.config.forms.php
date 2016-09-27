<?php

namespace Application;

use Zend\Validator as ZendValidator;

return [
    'form_elements' => [
        'invokables' => [
            Form\Element\BrandName::class => Form\Element\BrandName::class,
            Form\Element\BrandFullName::class => Form\Element\BrandFullName::class,
            Form\Element\EngineName::class => Form\Element\EngineName::class,
            Form\Element\FactoryName::class => Form\Element\FactoryName::class,
            Form\Element\Year::class => Form\Element\Year::class,
            Form\Element\UserPassword::class => Form\Element\UserPassword::class,
        ]
    ],
    'forms' => [
        'FeedbackForm' => [
            //'hydrator' => 'ObjectProperty',
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'feedback/title',
            ],
            'elements' => [
                [
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
                [
                    'spec' => [
                        'type' => 'Captcha',
                        'name' => 'captcha',
                        'options' => [
                            'label' => 'login/captcha',
                            'captcha' => [
                                'class'   => 'Image',
                                'font'    => __DIR__ . '/../assets/fonts/arial.ttf',
                                'imgDir'  => APPLICATION_PATH . '/../public_html/img/captcha/',
                                'imgUrl'  => '/img/captcha/',
                                'wordLen' => 4,
                                'timeout' => 300,
                            ]
                        ],
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
        'ForumsTopicNewForm' => [
            //'hydrator' => 'ObjectProperty',
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
                            'label'     => 'forums/topic/name',
                        ],
                        'attributes' => [
                            'size'      => 80,
                            'maxlength' => 100,
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Textarea',
                        'name' => 'text',
                        'options' => [
                            'label'     => 'forums/topic/text',
                        ],
                        'attributes' => [
                            'cols'      => 140,
                            'rows'      => 15,
                            'maxlength' => 1024*4
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Checkbox',
                        'name' => 'moderator_attention',
                        'options' => [
                            'label' => 'comments/it-requires-attention-of-moderators',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Checkbox',
                        'name' => 'subscribe',
                        'options' => [
                            'label' => 'forums/topic/subscribe-to-new-messages',
                        ]
                    ],
                ],
            ],
            'input_filter' => [
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
                                'max' => 1024*4
                            ]
                        ]
                    ]
                ],
            ],
        ],
        'CommentForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'comments/form-title',
                'id'     => 'form-add-comment'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Textarea',
                        'name' => 'message',
                        'options' => [
                            'label'     => 'forums/topic/text',
                        ],
                        'attributes' => [
                            'cols'      => 80,
                            'rows'      => 5,
                            'maxlength' => 1024*16
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Checkbox',
                        'name' => 'moderator_attention',
                        'options' => [
                            'label' => 'comments/it-requires-attention-of-moderators',
                        ]
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Hidden',
                        'name' => 'parent_id',
                    ],
                ],
                [
                    'spec' => [
                        'type' => 'Hidden',
                        'name' => 'resolve',
                    ],
                ]
            ],
            'input_filter' => [
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
                                'max' => 1024*16
                            ]
                        ]
                    ]
                ],
            ],
        ],
        'AddBrandForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post',
                'legend' => 'moder/brands/new/title',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => Form\Element\BrandName::class,
                        'name' => 'name'
                    ],
                ]
            ],
            'input_filter' => [
                'name' => [
                    'required'   => true,
                    'validators' => [
                        ['name' => Validator\Brand\NameNotExists::class]
                    ]
                ]
            ]
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
                [
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
                [
                    'spec' => [
                        'type' => Form\Element\UserName::class,
                        'name' => 'name'
                    ]
                ],
                [
                    'spec' => [
                        'type' => Form\Element\UserPassword::class,
                        'name' => 'password',
                    ]
                ],
                [
                    'spec' => [
                        'type' => Form\Element\UserPassword::class,
                        'name' => 'password_confirm',
                        'options' => [
                            'label' => 'user/password-confirm',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Captcha',
                        'name' => 'captcha',
                        'options' => [
                            'label' => 'login/captcha',
                            'captcha' => [
                                'class'   => 'Image',
                                'font'    => __DIR__ . '/../assets/fonts/arial.ttf',
                                'imgDir'  => APPLICATION_PATH . '/../public_html/img/captcha/',
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
                        'type' => Form\Element\UserPassword::class,
                        'name' => 'password'
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
        'MuseumForm' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method'  => 'post',
                'enctype' => 'multipart/form-data',
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'name',
                        'options' => [
                            'label'     => 'museum/name',
                            'maxlength' => 255,
                            'size'      => 80
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'url',
                        'options' => [
                            'label'     => 'URL',
                            'maxlength' => 255,
                            'size'      => 80
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'address',
                        'options' => [
                            'label'     => 'museum/address',
                            'maxlength' => 255,
                            'size'      => 80
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'lat',
                        'options' => [
                            'label'     => 'latitude',
                            'maxlength' => 20,
                            'size'      => 20
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'lng',
                        'options' => [
                            'label'     => 'longtitude',
                            'maxlength' => 20,
                            'size'      => 20
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'Textarea',
                        'name' => 'description',
                        'options' => [
                            'label' => 'museum/description',
                            'cols'  => 80,
                            'rows'  => 8
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type' => 'File',
                        'name' => 'photo',
                        'options' => [
                            'label' => 'museum/photo'
                        ]
                    ]
                ],
            ],
            'input_filter' => [
                'logo' => [
                    'required' => false,
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
                                'extension' => 'jpg,jpeg,jpe,png,gif,bmp'
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
                ],
                'name' => [
                    'required' => true,
                    'filters' => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'url' => [
                    'required' => false,
                    'filters' => [
                        ['name' => 'StringTrim']
                    ],
                    'validators' => [
                        ['name' => 'Uri']
                    ]
                ],
                'address' => [
                    'required' => true,
                    'filters' => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'lat' => [
                    'required' => true,
                    'filters' => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'lng' => [
                    'required' => true,
                    'filters' => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'description' => [
                    'required' => false,
                    'filters' => [
                        ['name' => 'StringTrim']
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
    ]
];
