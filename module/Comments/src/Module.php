<?php

declare(strict_types=1);

namespace Autowp\Comments;

use Laminas\Console\Adapter\AdapterInterface as Console;
use Laminas\Loader\StandardAutoloader;
use Laminas\ModuleManager\Feature;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\ConsoleUsageProviderInterface,
    Feature\ConsoleBannerProviderInterface,
    //Feature\ControllerProviderInterface,
    Feature\ConfigProviderInterface
{
    public function getConfig(): array
    {
        $provider = new ConfigProvider();
        return [
            'console'         => $provider->getConsoleConfig(),
            'controllers'     => $provider->getControllersConfig(),
            'service_manager' => $provider->getDependencyConfig(),
            'forms'           => $provider->getFormsConfig(),
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

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleBanner(Console $console): string
    {
        return __NAMESPACE__ . ' Module';
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleUsage(Console $console): array
    {
        return [
            'comments refresh-replies-count' => 'Refresh replies count',
            'comments cleanup-deleted'       => 'Cleanup deleted messages with expired ttl',
        ];
    }
}
