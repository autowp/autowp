<?php

namespace Autowp\Traffic;

use Laminas\EventManager\EventInterface as Event;
use Laminas\Loader\StandardAutoloader;
use Laminas\ModuleManager\Feature;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    //Feature\ControllerProviderInterface,
    Feature\ConfigProviderInterface
{
    public function getConfig(): array
    {
        $provider = new ConfigProvider();
        return [
            'controllers'     => $provider->getControllersConfig(),
            'service_manager' => $provider->getDependencyConfig(),
            'router'          => $provider->getRouterConfig(),
        ];
    }

    public function getAutoloaderConfig(): array
    {
        return [
            StandardAutoloader::class => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src',
                ],
            ],
        ];
    }

    public function onBootstrap(Event $e)
    {
        $trafficListener = new TrafficRouteListener();
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $trafficListener->attach($e->getApplication()->getEventManager());
    }
}
