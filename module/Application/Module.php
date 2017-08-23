<?php

namespace Application;

use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\EventManager\EventInterface as Event;
use Zend\Mail;
use Zend\ModuleManager\Feature;
use Zend\Mvc\MvcEvent;

class Module implements
    Feature\AutoloaderProviderInterface,
    Feature\BootstrapListenerInterface,
    Feature\ConsoleUsageProviderInterface,
    Feature\ConsoleBannerProviderInterface,
    Feature\ConfigProviderInterface
{
    const VERSION = '1.0dev';

    public function getConfig()
    {
        $config = [];

        $configFiles = [
            __DIR__ . '/config/module.config.php',
            __DIR__ . '/config/module.config.db.php',
            __DIR__ . '/config/module.config.api.php',
            __DIR__ . '/config/module.config.session.php',
            __DIR__ . '/config/module.config.cache.php',
            __DIR__ . '/config/module.config.console.php',
            __DIR__ . '/config/module.config.forms.php',
            __DIR__ . '/config/module.config.imagestorage.php',
            __DIR__ . '/config/module.config.routes.php',
            __DIR__ . '/config/module.config.tables.php',
            __DIR__ . '/config/module.config.moder.php',
            __DIR__ . '/config/module.config.log.php',
            __DIR__ . '/config/module.config.view.php',
        ];

        // Merge all module config options
        foreach ($configFiles as $configFile) {
            $config = \Zend\Stdlib\ArrayUtils::merge($config, include $configFile);
        }

        return $config;
    }

    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

    public function onBootstrap(Event $e)
    {
        error_reporting(E_ALL);
        ini_set('display_errors', true);

        defined('PUBLIC_DIR') || define('PUBLIC_DIR', realpath(__DIR__ . '/../../public_html'));

        defined('MYSQL_TIMEZONE') || define('MYSQL_TIMEZONE', 'UTC');
        defined('MYSQL_DATETIME_FORMAT') || define('MYSQL_DATETIME_FORMAT', 'Y-m-d H:i:s');

        \Zend\View\Helper\PaginationControl::setDefaultViewPartial('paginator');

        $application = $e->getApplication();
        $serviceManager = $application->getServiceManager();
        $eventManager = $application->getEventManager();

        //handle the dispatch error (exception)
        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'handleError']);
        //handle the view render error (exception)
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'handleError']);

        $sessionListener = new SessionDispatchListener();
        $sessionListener->onBootstrap($e);

        $lastOnlineListener = new UserLastOnlineDispatchListener();
        $lastOnlineListener->attach($eventManager);

        $urlCorrectionListener = new UrlCorrectionRouteListener();
        $urlCorrectionListener->attach($eventManager);

        $serviceManager->get(HostnameCheckRouteListener::class)->attach($eventManager);

        $languageListener = new LanguageRouteListener();
        $languageListener->attach($eventManager);

        $maintenance = new Maintenance();
        $maintenance->attach($serviceManager->get('CronEventManager'));
    }

    public function handleError(MvcEvent $e)
    {
        $exception = $e->getParam('exception');
        if ($exception) {
            $serviceManager = $e->getApplication()->getServiceManager();
            $serviceManager->get('ErrorLog')->crit($exception);

            $filePath = __DIR__ . '/../../data/email-error';
            if (file_exists($filePath)) {
                $mtime = filemtime($filePath);
                $diff = time() - $mtime;
                if ($diff > 60) {
                    touch($filePath);
                    $this->sendErrorEmail($exception, $serviceManager);
                }
            }
        }
    }

    private function sendErrorEmail($exception, $serviceManager)
    {
        $message = get_class($exception) . PHP_EOL .
                   'File: ' . $exception->getFile() . PHP_EOL .
                   'Message: ' . $exception->getMessage() . PHP_EOL .
                   'Trace: ' . PHP_EOL . $exception->getTraceAsString() . PHP_EOL;

        $mail = new Mail\Message();
        $mail
            ->setEncoding('utf-8')
            ->setBody($message)
            ->setFrom('no-reply@autowp.ru', 'robot autowp.ru')
            ->addTo('dvp@autowp.ru')
            ->setSubject('autowp exception: ' . get_class($exception));

        $transport = $serviceManager->get(Mail\Transport\TransportInterface::class);
        $transport->send($mail);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleBanner(Console $console)
    {
        return 'WheelsAge Module';
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleUsage(Console $console)
    {
        //description command
        return [
            'db_migrations_version'             => 'Get current migration version',
            'db_migrations_migrate [<version>]' => 'Execute migrate',
            'db_migrations_generate'            => 'Generate new migration class'
        ];
    }
}
