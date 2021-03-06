<?php

namespace Autowp\Traffic;

use Laminas\EventManager\EventInterface as Event;
use Laminas\Loader\StandardAutoloader;
use Laminas\ModuleManager\Feature;
use Laminas\Mvc\MvcEvent;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    Feature\ConfigProviderInterface
{
    public function getConfig(): array
    {
        $provider = new ConfigProvider();
        return [
            'service_manager' => $provider->getDependencyConfig(),
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

    /**
     * @param MvcEvent $e
     */
    public function onBootstrap(Event $e): void
    {
        $trafficListener = new TrafficRouteListener();
        $trafficListener->attach($e->getApplication()->getEventManager());
    }
}
