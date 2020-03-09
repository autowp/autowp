<?php

namespace Autowp\Traffic;

use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'controllers'  => $this->getControllersConfig(),
            'dependencies' => $this->getDependencyConfig(),
            'router'       => $this->getRouterConfig(),
        ];
    }

    public function getControllersConfig(): array
    {
        return [
            'factories' => [
                Controller\BanController::class => Controller\BanControllerFactory::class,
            ],
        ];
    }

    /**
     * Return application-level dependency configuration.
     */
    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                TrafficControl::class => Service\TrafficControlFactory::class,
            ],
        ];
    }

    public function getRouterConfig(): array
    {
        return [
            'routes' => [
                'ban' => [
                    'type'          => Literal::class,
                    'options'       => [
                        'route'    => '/ban',
                        'defaults' => [
                            'controller' => Controller\BanController::class,
                            'action'     => 'index',
                        ],
                    ],
                    'may_terminate' => false,
                    'child_routes'  => [
                        'ban-ip'   => [
                            'type'    => Segment::class,
                            'options' => [
                                'route'    => '/ban-ip/ip/:ip',
                                'defaults' => [
                                    'action' => 'ban-ip',
                                ],
                            ],
                        ],
                        'unban-ip' => [
                            'type'    => Segment::class,
                            'options' => [
                                'route'    => '/unban-ip/ip/:ip',
                                'defaults' => [
                                    'action' => 'unban-ip',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
