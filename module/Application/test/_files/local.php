<?php

namespace Application;

return [
    'forms' => [
        'FeedbackForm' => [
            'elements' => [
                'captcha' => [
                    'spec' => [
                        'options' => [
                            'captcha' => [
                                'imgDir' => sys_get_temp_dir()
                            ]
                        ],
                    ],
                ],
            ]
        ],
        'RegistrationForm' => [
            'elements' => [
                'captcha' => [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'captcha'
                    ],
                ]
            ],
            'input_filter' => [
                'captcha' => [
                    'required' => false
                ]
            ]
        ],
    ],
];
