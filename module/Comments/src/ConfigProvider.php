<?php

namespace Autowp\Comments;

class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke()
    {
        return [
            'console'      => $this->getConsoleConfig(),
            'controllers'  => $this->getControllersConfig(),
            'dependencies' => $this->getDependencyConfig(),
            'forms'        => $this->getFormsConfig()
        ];
    }

    /**
     * @return array
     */
    public function getConsoleConfig()
    {
        return [
            'router' => [
                'routes' => [
                    'comments' => [
                        'options' => [
                            'route'    => 'comments (refresh-replies-count):action',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class,
                            ]
                        ]
                    ],
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function getControllersConfig()
    {
        return [
            'factories' => [
                Controller\ConsoleController::class => Controller\ConsoleControllerFactory::class
            ]
        ];
    }

    /**
     * Return application-level dependency configuration.
     *
     * @return array
     */
    public function getDependencyConfig()
    {
        return [
            'factories' => [
                CommentsService::class => CommentsServiceFactory::class
            ]
        ];
    }

    public function getFormsConfig()
    {
        return [
            'CommentForm' => [
                'type'     => \Zend\Form\Form::class,
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
            ]
        ];
    }
}
