<?php

namespace Application;

use Laminas\Console\Adapter\AdapterInterface as Console;
use Laminas\EventManager\EventInterface as Event;
use Laminas\Loader\StandardAutoloader;
use Laminas\ModuleManager\Feature;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Laminas\View\Helper\PaginationControl;

use function error_reporting;
use function ini_set;
use function Sentry\captureException;
use function Sentry\init;

use const E_ALL;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    Feature\ConsoleUsageProviderInterface,
    Feature\ConsoleBannerProviderInterface,
    Feature\ConfigProviderInterface
{
    public const VERSION               = '1.0dev';
    public const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const MYSQL_TIMEZONE        = 'UTC';

    public function getConfig(): array
    {
        $config = [];

        $configFiles = [
            __DIR__ . '/../config/module.config.php',
            __DIR__ . '/../config/module.config.db.php',
            __DIR__ . '/../config/module.config.api.php',
            __DIR__ . '/../config/module.config.api.filter.php',
            __DIR__ . '/../config/module.config.cache.php',
            __DIR__ . '/../config/module.config.console.php',
            __DIR__ . '/../config/module.config.forms.php',
            __DIR__ . '/../config/module.config.imagestorage.php',
            __DIR__ . '/../config/module.config.routes.php',
            __DIR__ . '/../config/module.config.tables.php',
            __DIR__ . '/../config/module.config.moder.php',
            __DIR__ . '/../config/module.config.view.php',
            __DIR__ . '/../config/module.config.rabbitmq.php',
        ];

        // Merge all module config options
        foreach ($configFiles as $configFile) {
            $config = ArrayUtils::merge($config, include $configFile);
        }

        return $config;
    }

    public function getAutoloaderConfig(): array
    {
        return [
            StandardAutoloader::class => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

    /**
     * @param MvcEvent $e
     */
    public function onBootstrap(Event $e): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        PaginationControl::setDefaultViewPartial('paginator');

        /** @var Application $application */
        $application = $e->getApplication();
        /** @var ServiceLocatorInterface $serviceManager */
        $serviceManager = $application->getServiceManager();
        $eventManager   = $application->getEventManager();

        //handle the dispatch error (exception)
        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'handleError']);
        //handle the view render error (exception)
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'handleError']);

        $lastOnlineListener = new UserLastOnlineDispatchListener();
        $lastOnlineListener->attach($eventManager);

        $urlCorrectionListener = new UrlCorrectionRouteListener();
        $urlCorrectionListener->attach($eventManager);

        $config = $serviceManager->get('Config');

        if (isset($config['sentry']['dsn']) && $config['sentry']['dsn']) {
            init([
                'dsn'         => $config['sentry']['dsn'],
                'environment' => $config['sentry']['environment'],
                'release'     => $config['sentry']['release'],
            ]);
        }

        $languageListener = new LanguageRouteListener();
        $languageListener->attach($eventManager);

        $maintenance = new Maintenance();
        $maintenance->attach($serviceManager->get('CronEventManager'));
    }

    public function handleError(MvcEvent $e): void
    {
        $exception = $e->getParam('exception');
        if ($exception) {
            captureException($exception);
        }
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleBanner(Console $console): string
    {
        return 'WheelsAge Module';
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleUsage(Console $console): array
    {
        return [];
    }
}
