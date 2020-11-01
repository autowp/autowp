<?php

namespace Autowp\Message;

use Laminas\EventManager\EventInterface;
use Laminas\Loader\StandardAutoloader;
use Laminas\ModuleManager\Feature;
use Laminas\Mvc\MvcEvent;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    Feature\ConfigProviderInterface
{
    /**
     * @param MvcEvent $e
     */
    public function onBootstrap(EventInterface $e): void
    {
        $application    = $e->getApplication();
        $serviceManager = $application->getServiceManager();

        $maintenance = new Maintenance();
        $maintenance->attach($serviceManager->get('CronEventManager')); // TODO: move CronEventManager to zf-components
    }

    public function getConfig(): array
    {
        $provider = new ConfigProvider();
        return [
            'service_manager' => $provider->getDependencyConfig(),
            'tables'          => $provider->getTablesConfig(),
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
}
