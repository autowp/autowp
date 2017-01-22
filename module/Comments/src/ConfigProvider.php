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
            'dependencies' => $this->getDependencyConfig()
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
}
