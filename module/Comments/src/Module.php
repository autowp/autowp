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
            'service_manager' => $provider->getDependencyConfig(),
            'forms'           => $provider->getFormsConfig()
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
            'comments refresh-replies-count' => 'Refresh replies count',
            'comments cleanup-deleted' => 'Cleanup deleted messages with expired ttl',
        ];
    }
}
