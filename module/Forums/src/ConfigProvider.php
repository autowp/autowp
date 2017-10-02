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
                        'topic-message' => [
                            'type' => Segment::class,
                            'options' => [
                                'route' => '/topic-message/message_id/:message_id',
                                'defaults' => [
                                    'action' => 'topic-message',
                                ],
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

    public function getTablesConfig(): array
    {
        return [
            'forums_themes' => [],
            'forums_topics' => [],
        ];
    }
}
