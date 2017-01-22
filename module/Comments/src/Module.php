<?php

namespace Autowp\Comments;

use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\ModuleManager\Feature;

class Module implements
    Feature\AutoloaderProviderInterface,
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
            'service_manager' => $provider->getDependencyConfig()
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

    public function getConsoleBanner(Console $console)
    {
        return __NAMESPACE__ . ' Module';
    }

    public function getConsoleUsage(Console $console)
    {
        return [
            'message clear-old-system-pm' => 'Clear old system messages',
            'message clear-deleted-pm'    => 'Clear deleted messages'
        ];
    }
}
