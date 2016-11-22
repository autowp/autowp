<?php

namespace Autowp\User;

use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\EventManager\EventInterface as Event;
use Zend\ModuleManager\Feature;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    Feature\ConsoleUsageProviderInterface,
    Feature\ConsoleBannerProviderInterface,
    Feature\ControllerProviderInterface,
    Feature\ConfigProviderInterface
{
    /**
     * @return array
     */
    public function getConfig()
    {
        $provider = new ConfigProvider();
        return [
            'console'     => $provider->getConsoleConfig(),
            'controllers' => $provider->getControllersConfig(),
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
        $application = $e->getApplication();
        $eventManager = $application->getEventManager();

        $authRememberListener = new Auth\RememberDispatchListener();
        $authRememberListener->attach($eventManager);
    }

    public function getConsoleBanner(Console $console)
    {
        return __NAMESPACE__ . ' Module';
    }

    public function getConsoleUsage(Console $console)
    {
        return [
            'users clear-password-remind' => 'Clear old password remind tokens',
            'users clear-remember'        => 'Clear old remember tokens',
            'users clear-renames'         => 'Clear old renames',
        ];
    }

    public function getControllerConfig()
    {
        $provider = new ConfigProvider();
        return $provider->getControllersConfig();
    }
}