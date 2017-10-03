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
                        'topic-message' => [
                            'type' => Segment::class,
                            'options' => [
                                'route' => '/topic-message/message_id/:message_id',
                                'defaults' => [
                                    'action' => 'topic-message',
                                ],
                            ]
                        ]
                    ]
                ]
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
