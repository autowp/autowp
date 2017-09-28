<?php

namespace Application;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'moder' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/moder'
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'attrs' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/attrs[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\AttrsController::class,
                                'action'     => 'index'
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'params' => [
                                'type' => Router\Http\WildcardSafe::class
                            ]
                        ]
                    ],
                    'modification' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/modification[/:action]',
                            'defaults' => [
                                'controller' => Controller\Moder\ModificationController::class,
                                'action'     => 'index'
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'params' => [
                                'type' => Router\Http\WildcardSafe::class
                            ]
                        ]
                    ]
                ]
            ],
        ]
    ],
    'controllers' => [
        'factories' => [
            Controller\Moder\AttrsController::class => Controller\Moder\Service\AttrsControllerFactory::class,
        ]
    ],
    'forms' => [
        'ModerPictureVoteForm2' => [
            'type'     => 'Zend\Form\Form',
            'attributes'  => [
                'method' => 'post'
            ],
            'elements' => [
                [
                    'spec' => [
                        'type' => 'Text',
                        'name' => 'reason',
                        'options' => [
                            'label' => 'moder/picture/acceptance/reason',
                        ],
                        'attributes' => [
                            'size'      => Model\PictureModerVote::MAX_LENGTH,
                            'maxlength' => Model\PictureModerVote::MAX_LENGTH,
                            'class'     => 'form-control',
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type'    => 'Select',
                        'name'    => 'vote',
                        'options' => [
                            'options' => [
                                 '1' => 'moder/picture/acceptance/want-accept',
                                '-1' => 'moder/picture/acceptance/want-delete'
                            ]
                        ]
                    ]
                ],
                [
                    'spec' => [
                        'type'    => 'Checkbox',
                        'name'    => 'save',
                        'options' => [
                            'label' => 'Save as template?',
                        ],
                    ]
                ]
            ],
            'input_filter' => [
                'reason' => [
                    'required' => true,
                    'filters'  => [
                        ['name' => 'StringTrim']
                    ]
                ],
                'vote' => [
                    'required' => true
                ],
                'save' => [
                    'required' => false
                ]
            ]
        ]
    ]
];