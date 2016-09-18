<?php
/**
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Zend\EventManager\EventInterface as Event;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\ModuleManager\Feature\ConfigProviderInterface;

use Project_Auth_Adapter_Remember;
use Users;
use Zend_Auth;
use Zend_Cache_Manager;
use Zend_Date;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Locale;
use Zend_Locale_Data;
use Zend_Locale_Exception;
use Zend_Registry;

class Module implements ConsoleUsageProviderInterface,
    ConsoleBannerProviderInterface, ConfigProviderInterface
{
    const VERSION = '1.0dev';

    /**
     * @var array
     */
    private $hostnameWhitelist = [
        'www.autowp.ru', 'ru.autowp.ru', 'en.autowp.ru',
        'i.wheelsage.org', 'en.wheelsage.org', 'fr.wheelsage.org',
        'zh.wheelsage.org', 'www.wheelsage.org', 'wheelsage.org'
    ];

    /**
     * @var string
     */
    private $defaultHostname = 'www.autowp.ru';

    /**
     * @var array
     */
    private $languageWhitelist = ['ru', 'en', 'fr', 'zh'];

    /**
     * @var array
     */
    private $whitelist = [
        'fr.wheelsage.org' => 'fr',
        'en.wheelsage.org' => 'en',
        'zh.wheelsage.org' => 'zh',
        'autowp.ru'        => 'ru',
        'www.autowp.ru'    => 'ru',
        'ru.autowp.ru'     => 'ru'
    ];

    /**
     * @var array
     */
    private $redirects = [
        'www.wheelsage.org' => 'en.wheelsage.org',
        'wheelsage.org'     => 'en.wheelsage.org',
        'en.autowp.ru'      => 'en.wheelsage.org',
        'ru.autowp.ru'      => 'www.autowp.ru'
    ];

    /**
     * @var array
     */
    private $userDetectable = [
        'wheelsage.org'
    ];

    /**
     * @var array
     */
    private $skipHostname = ['i.wheelsage.org'];

    /**
     * @var string
     */
    private $defaultLanguage = 'en';

    public function getConfig()
    {
        //return include __DIR__ . '/config/module.config.php';

        $config = [];

        $configFiles = [
            __DIR__ . '/config/module.config.php',
            __DIR__ . '/config/module.config.cache.php',
            __DIR__ . '/config/module.config.console.php',
            __DIR__ . '/config/module.config.forms.php',
            __DIR__ . '/config/module.config.imagestorage.php',
            __DIR__ . '/config/module.config.routes.php',
            __DIR__ . '/config/module.config.moder.php',
        ];

        // Merge all module config options
        foreach($configFiles as $configFile) {
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
        defined('PUBLIC_DIR') || define('PUBLIC_DIR', realpath(APPLICATION_PATH . '/../public_html'));
        defined('RESOURCES_DIR') || define('RESOURCES_DIR', APPLICATION_PATH . '/resources');
        defined('IMAGES_DIR') || define('IMAGES_DIR', PUBLIC_DIR . '/img');
        defined('IMAGES_URL') || define('IMAGES_URL', '/img');

        defined('MYSQL_DATE') || define('MYSQL_DATE', 'yyyy-MM-dd');
        defined('MYSQL_TIME') || define('MYSQL_TIME', 'HH:mm:ss');
        defined('MYSQL_DATETIME') || define('MYSQL_DATETIME', MYSQL_DATE . ' ' . MYSQL_TIME);
        defined('MYSQL_TIMEZONE') || define('MYSQL_TIMEZONE', 'UTC');
        defined('MYSQL_DATETIME_FORMAT') || define('MYSQL_DATETIME_FORMAT', 'Y-m-d H:i:s');

        set_include_path(APPLICATION_PATH . '/../library' . PATH_SEPARATOR . get_include_path());

        $serviceManager = $e->getApplication()->getServiceManager();

        $cacheManager = $serviceManager->get(Zend_Cache_Manager::class);

        Zend_Registry::set('Cachemanager', $cacheManager);

        $serviceManager->get(Zend_Db_Adapter_Abstract::class);
        $serviceManager->get('session');

        $this->initLocaleAndTranslate($cacheManager);

        error_reporting(E_ALL);
        ini_set('display_errors', true);

        $eventManager = $e->getApplication()->getEventManager();
        $eventManager->attach( \Zend\Mvc\MvcEvent::EVENT_DISPATCH, [$this, 'preDispatch'], 100 );

        \Zend\View\Helper\PaginationControl::setDefaultViewPartial('paginator');
    }


    private function initLocaleAndTranslate($cacheManager)
    {
        $longCache = $cacheManager->getCache('long');
        $localeCache = $cacheManager->getCache('locale');

        Zend_Locale_Data::setCache($localeCache);
        Zend_Date::setOptions(['cache' => $localeCache]);
    }

    public function getConsoleBanner(Console $console)
    {
        return 'WheelsAge Module';
    }

    public function getConsoleUsage(Console $console)
    {
        //description command
        return [
            'db_migrations_version'             => 'Get current migration version',
            'db_migrations_migrate [<version>]' => 'Execute migrate',
            'db_migrations_generate'            => 'Generate new migration class'
        ];
    }

    public function preDispatch($e)
    {
        $this->hostnameCheck($e);
        $this->languagePreDispatch($e);
        $this->urlCorrection($e);
        $this->authRemember($e);
        $this->traffic($e);
        $this->lastOnline($e);
    }

    private function languagePreDispatch($e)
    {
        $app = $e->getApplication();
        $serviceManager = $app->getServiceManager();

        $request = $serviceManager->get('Request');

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {

            $language = $this->defaultLanguage;

            $hostname = $request->getServer('HTTP_HOST');

            if (in_array($hostname, $this->skipHostname)) {
                $uri = $request->getUriString();
                //if (substr($uri, 0, strlen($this->_skipUrl)) == $this->_skipUrl) {
                    return;
                //}
            }

            if (in_array($hostname, $this->userDetectable)) {
                $userLanguage = $this->detectUserLanguage();

                $hosts = $this->getConfig()['hosts'];

                if (isset($hosts[$userLanguage])) {
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                            $hosts[$userLanguage]['hostname'] . $request->getRequestUri();

                    $this->redirect($app, $redirectUrl);
                    return;
                }
            }

            if (isset($this->redirects[$hostname])) {

                $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $this->redirects[$hostname] . $request->getRequestUri();

                $this->redirect($app, $redirectUrl);
                return;
            }

            if (isset($this->whitelist[$hostname])) {
                $language = $this->whitelist[$hostname];
            } else {

                try {
                    $locale = new Zend_Locale(Zend_Locale::BROWSER);
                    $localeLanguage = $locale->getLanguage();
                    $isAllowed = in_array($localeLanguage, $this->languageWhitelist);
                    if ($isAllowed) {
                        $language = $localeLanguage;
                    }
                } catch (Zend_Locale_Exception $e) {
                }
            }

            $this->initLocaleAndTranslate2($serviceManager, $language);
        }
    }

    private function detectUserLanguage()
    {
        $result = null;

        $auth = Zend_Auth::getInstance();
        if ($auth->hasIdentity()) {

            $userTable = new Users();

            $user = $userTable->find($auth->getIdentity())->current();

            if ($user) {
                $isAllowed = in_array($user->language, $this->languageWhitelist);
                if ($isAllowed) {
                    $result = $user->language;
                }
            }
        }

        return $result;
    }

    /**
     * @param string $language
     */
    private function initLocaleAndTranslate2($serviceManager, $language)
    {
        // Locale
        $locale = new Zend_Locale($language);

        // populate for wide-engine
        Zend_Registry::set('Zend_Locale', $locale);

        $translator = $serviceManager->get('MvcTranslator');
        $translator->setLocale($language);
    }

    private function hostnameCheck($e)
    {
        $app = $e->getApplication();
        $serviceManager = $app->getServiceManager();

        $request = $serviceManager->get('Request');

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {
            $hostname = $request->getServer('HTTP_HOST');

            $isAllowed = in_array($hostname, $this->hostnameWhitelist);

            if (!$isAllowed) {
                //$request->setDispatched(true);

                $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $this->defaultHostname . $request->getRequestUri();

                $this->redirect($app, $redirectUrl);
            }
        }
    }

    private function redirect($app, $url)
    {
        $app->getEventManager()->getSharedManager()->attach('Zend\Mvc\Controller\AbstractActionController', 'dispatch', function($e) use ($url) {
            $controller = $e->getTarget();
            $controller->plugin('redirect')->toUrl($url);
        }, 100);
    }

    private function authRemember($e)
    {
        $app = $e->getApplication();
        $serviceManager = $app->getServiceManager();

        $request = $serviceManager->get('Request');

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {

            $auth = Zend_Auth::getInstance();
            if (!$auth->hasIdentity()) {
                $cookies = $request->getCookie();
                if ($cookies && isset($cookies['remember'])) {
                    $adapter = new Project_Auth_Adapter_Remember();
                    $adapter->setCredential($cookies['remember']);
                    $result = $auth->authenticate($adapter);
                }
            }
        }
    }

    private function urlCorrection($e)
    {
        $app = $e->getApplication();
        $serviceManager = $app->getServiceManager();

        $request = $serviceManager->get('Request');

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {
            $uri = $request->getRequestUri();

            $method = $request->getMethod();

            if ($method == 'GET') {

                $filteredUri = preg_replace('|^/index\.php|isu', '', $uri);

                if ($filteredUri != $uri) {
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                            $request->getHttpHost() . $filteredUri;

                    $this->redirect($app, $redirectUrl);
                }
            }

            $pattern = '/pictures/';
            $host = 'i.wheelsage.org';
            if (strncmp($uri, $pattern, strlen($pattern)) == 0) {
                if ($request->getUri()->getHost() != $host) {
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                            $host . $uri;

                    $this->redirect($app, $redirectUrl);
                }
            }
        }
    }

    public function traffic($e)
    {
        $app = $e->getApplication();
        $serviceManager = $app->getServiceManager();

        $request = $serviceManager->get('Request');

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {

            $auth = Zend_Auth::getInstance();

            $unlimitedTraffic = false;
            if ($auth->hasIdentity()) {
                $userId = $auth->getIdentity();
                $userTable = new Users();
                $user = $userTable->find($userId)->current();

                if ($user) {
                    $acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
                    $unlimitedTraffic = $acl->isAllowed($user->role, 'website', 'unlimited-traffic');
                }
            }

            $ip = $request->getServer('REMOTE_ADDR');

            $service = new Service\TrafficControl();

            $banInfo = $service->getBanInfo($ip);
            if ($banInfo) {
                header('HTTP/1.1 509 Bandwidth Limit Exceeded');
                print 'Access denied: ' . $banInfo['reason'];
                exit;
            }

            if (!$unlimitedTraffic && !$service->inWhiteList($ip)) {
                $service->pushHit($ip);
            }
        }
    }

    public function lastOnline($e)
    {
        $app = $e->getApplication();
        $serviceManager = $app->getServiceManager();

        $request = $serviceManager->get('Request');

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {

            $auth = Zend_Auth::getInstance();
            if ($auth->hasIdentity()) {

                $userTable = new Users();

                $user = $userTable->find($auth->getIdentity())->current();

                if ($user) {
                    $changes = false;
                    $nowExpiresDate = Zend_Date::now()->subMinute(1);
                    $lastOnline = $user->getDate('last_online');
                    if (!$lastOnline || ($lastOnline->isEarlier($nowExpiresDate))) {
                        $user->last_online = new Zend_Db_Expr('NOW()');
                        $changes = true;
                    }

                    $ip = inet_pton($request->getServer('REMOTE_ADDR'));
                    if ($ip != $user->last_ip) {
                        $user->last_ip = $ip;
                        $changes = true;
                    }

                    if ($changes) {
                        $user->save();
                    }
                }
            }
        }
    }
}
