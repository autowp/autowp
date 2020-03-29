<?php

namespace Autowp\User;

use Laminas\EventManager\EventInterface as Event;
use Laminas\Loader\StandardAutoloader;
use Laminas\ModuleManager\Feature;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    Feature\ConfigProviderInterface
{
    public function getConfig(): array
    {
        $provider = new ConfigProvider();
        return [
            'controller_plugins' => $provider->getControllerPluginConfig(),
            'service_manager'    => $provider->getDependencyConfig(),
            'tables'             => $provider->getTablesConfig(),
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
     * @suppress PhanUndeclaredMethod
     */
    public function onBootstrap(Event $e): array
    {
        $application    = $e->getApplication();
        $serviceManager = $application->getServiceManager();

        $maintenance = new Maintenance();
        $maintenance->attach($serviceManager->get('CronEventManager')); // TODO: move CronEventManager to zf-components

        return [];
    }
}
