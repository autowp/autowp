<?php

namespace Autowp\Forums;

use Zend\Router\Http\Segment;
use Zend\Router\Http\Literal;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'controllers'  => $this->getControllersConfig(),
            'dependencies' => $this->getDependencyConfig(),
            'forms'        => $this->getFormsConfig(),
            'router'       => $this->getRouterConfig(),
            'tables'       => $this->getTablesConfig(),
            'translator'   => $this->getTranslatorConfig()
        ];
    }

    public function getControllersConfig(): array
    {
        return [
            'factories' => [
                Controller\FrontendController::class => Controller\FrontendControllerFactory::class
            ]
        ];
    }

    public function getRouterConfig(): array
    {
        return [
            'routes' => [
                'forums' => [
                    'type' => Literal::class,
                    'options' => [
                        'route'    => '/forums',
                        'defaults' => [
                            'controller' => Controller\FrontendController::class
                        ],
                    ],
                    'may_terminate' => false,
                    'child_routes'  => [
                        'topic' => [
                            'type' => Segment::class,
                            'options' => [
                                'route' => '/topic/:topic_id[/page:page]',
                                'defaults' => [
                                    'action' => 'topic',
                                ],
                            ]
                        ],
                        'subscribe' => [
                            'type' => Segment::class,
                            'options' => [
                                'route' => '/subscribe/topic_id/:topic_id',
                                'defaults' => [
                                    'action' => 'subscribe',
                                ],
                            ]
                        ],
                        'unsubscribe' => [
                            'type' => Segment::class,
                            'options' => [
                                'route' => '/unsubscribe/topic_id/:topic_id',
                                'defaults' => [
                                    'action' => 'unsubscribe',
                                ],
                            ]
                        ],
                        'new' => [
                            'type' => Segment::class,
                            'options' => [
                                'route' => '/new/theme_id/:theme_id',
                                'defaults' => [
                                    'action' => 'new',
                                ],
                            ]
                        ],
                        'topic-message' => [
                            'type' => Segment::class,
                            'options' => [
                                'route' => '/topic-message/message_id/:message_id',
                                'defaults' => [
                                    'action' => 'topic-message',
                                ],
                            ]
                        ],
                        'open' => [
                            'type' => Literal::class,
                            'options' => [
                                'route' => '/open',
                                'defaults' => [
                                    'action' => 'open',
                                ]
                            ]
                        ],
                        'close' => [
                            'type' => Literal::class,
                            'options' => [
                                'route' => '/close',
                                'defaults' => [
                                    'action' => 'close',
                                ]
                            ]
                        ],
                        'delete' => [
                            'type' => Literal::class,
                            'options' => [
                                'route' => '/delete',
                                'defaults' => [
                                    'action' => 'delete',
                                ]
                            ]
                        ],
                        'move' => [
                            'type' => Segment::class,
                            'options' => [
                                'route' => '/move/:topic_id',
                                'defaults' => [
                                    'action' => 'move',
                                ]
                            ]
                        ],
                        'move-message' => [
                            'type' => Segment::class,
                            'options' => [
                                'route' => '/move-message/:id',
                                'defaults' => [
                                    'action' => 'move-message',
                                ]
                            ]
                        ],
                        'subscribes' => [
                            'type' => Literal::class,
                            'options' => [
                                'route' => '/subscribes',
                                'defaults' => [
                                    'action' => 'subscribes',
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    public function getTranslatorConfig(): array
    {
        return [
            'translation_file_patterns' => [
                [
                    'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                    'base_dir' => Resources::getBasePath(),
                    'pattern'  => Resources::getPattern()
                ],
            ]
        ];
    }

    /**
     * Return application-level dependency configuration.
     */
    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                Forums::class => ForumsFactory::class,
            ]
        ];
    }

    public function getFormsConfig(): array
    {
        return [
            'ForumsTopicNewForm' => [
                'type'     => \Zend\Form\Form::class,
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
                                'maxlength' => 1024 * 4
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
                                    'max' => 1024 * 4
                                ]
                            ]
                        ]
                    ],
                ],
            ],
        ];
    }

    public function getTablesConfig(): array
    {
        return [
            'forums_themes' => [],
            'forums_topics' => [],
        ];
    }
}
