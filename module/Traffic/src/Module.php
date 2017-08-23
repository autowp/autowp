<?php

namespace Autowp\Traffic;

use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\EventManager\EventInterface as Event;
use Zend\ModuleManager\Feature;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    Feature\ConsoleUsageProviderInterface,
    Feature\ConsoleBannerProviderInterface,
    //Feature\ControllerProviderInterface,
    Feature\ConfigProviderInterface
{
    /**
     * @return array
     */
    public function getConfig()
    {
        $provider = new ConfigProvider();
        return [
            'console'         => $provider->getConsoleConfig(),
            'controllers'     => $provider->getControllersConfig(),
            'service_manager' => $provider->getDependencyConfig(),
            'router'          => $provider->getRouterConfig(),
            'tables'          => $provider->getTablesConfig(),
        ];
    }

    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src',
                ],
            ],
        ];
    }

    public function onBootstrap(Event $e)
    {
        $trafficListener = new TrafficRouteListener();
        $trafficListener->attach($e->getApplication()->getEventManager());
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleBanner(Console $console)
    {
        return __NAMESPACE__ . ' Module';
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleUsage(Console $console)
    {
        return [
            'traffic autoban|google|gc|clear-referer-monitoring' => 'Usage'
        ];
    }

    /*public function getControllerConfig()
    {
        $provider = new ConfigProvider();
        return $provider->getControllersConfig();
    }*/
}
