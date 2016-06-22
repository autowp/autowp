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
use Zend_Registry;
use Zend_Translate;

class Module implements ConsoleUsageProviderInterface,
    ConsoleBannerProviderInterface, ConfigProviderInterface
{
    const VERSION = '1.0dev';

    /**
     * @var array
     */
    protected $_hostnameWhitelist = [
        'www.autowp.ru', 'ru.autowp.ru', 'en.autowp.ru',
        'i.wheelsage.org', 'en.wheelsage.org', 'fr.wheelsage.org',
        'www.wheelsage.org', 'wheelsage.org'
    ];

    /**
     * @var string
     */
    protected $_defaultHostname = 'www.autowp.ru';

    /**
     * @var array
     */
    private $_languageWhitelist = ['ru', 'en', 'fr'];

    /**
     * @var array
     */
    private $_whitelist = array(
        'fr.wheelsage.org' => 'fr',
        'en.wheelsage.org' => 'en',
        'autowp.ru'        => 'ru',
        'www.autowp.ru'    => 'ru',
        'ru.autowp.ru'     => 'ru'
    );

    /**
     * @var array
     */
    private $_redirects = array(
        'wheelsage.org' => 'en.wheelsage.org',
        'en.autowp.ru'  => 'en.wheelsage.org',
        'ru.autowp.ru'  => 'www.autowp.ru'
    );

    /**
     * @var array
     */
    private $_userDetectable = [
        'wheelsage.org'
    ];

    /**
     * @var array
     */
    private $_skipHostname = ['i.wheelsage.org'];

    /**
     * @var string
     */
    private $_defaultLanguage = 'en';

    /**
     * @var string
     */
    private $_hostname = '.autowp.ru';

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
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

        $config = $this->getConfig();

        $serviceManager = $e->getApplication()->getServiceManager();

        $cacheManager = $serviceManager->get(Zend_Cache_Manager::class);

        Zend_Registry::set('Cachemanager', $cacheManager);

        $serviceManager->get(Zend_Db_Adapter_Abstract::class);
        $serviceManager->get('session');

        $this->initLocaleAndTranslate();

        error_reporting(E_ALL);
        ini_set('display_errors', true);

        $eventManager = $e->getApplication()->getEventManager();
        $eventManager->attach( \Zend\Mvc\MvcEvent::EVENT_DISPATCH, array($this, 'preDispatch'), 100 );

        \Zend\View\Helper\PaginationControl::setDefaultViewPartial('paginator');
    }


    private function initLocaleAndTranslate()
    {
        $cachemanager = Zend_Registry::get('Cachemanager');

        $longCache = $cachemanager->getCache('long');
        $localeCache = $cachemanager->getCache('locale');

        Zend_Locale_Data::setCache($localeCache);
        Zend_Date::setOptions(['cache' => $localeCache]);
        Zend_Translate::setCache($longCache);
    }

    public function getConsoleBanner(Console $console) {
        return 'WheelsAge Module';
    }

    public function getConsoleUsage(Console $console) {
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

            $language = $this->_defaultLanguage;

            $hostname = $request->getServer('HTTP_HOST');

            if (in_array($hostname, $this->_skipHostname)) {
                $uri = $request->getUriString();
                if (substr($uri, 0, strlen($this->_skipUrl)) == $this->_skipUrl) {
                    return;
                }
            }

            if (in_array($hostname, $this->_userDetectable)) {
                $userLanguage = $this->_detectUserLanguage();

                $hosts = $this->getConfig()['hosts'];

                if (isset($hosts[$userLanguage])) {
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                            $hosts[$userLanguage]['hostname'] . $request->getRequestUri();

                    $this->redirect($app, $redirectUrl);
                    return;
                }
            }

            if (isset($this->_redirects[$hostname])) {

                $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $this->_redirects[$hostname] . $request->getRequestUri();

                $this->redirect($app, $redirectUrl);
                return;
            }

            if (isset($this->_whitelist[$hostname])) {
                $language = $this->_whitelist[$hostname];
            } else {

                try {
                    $locale = new Zend_Locale(Zend_Locale::BROWSER);
                    $localeLanguage = $locale->getLanguage();
                    $isAllowed = in_array($localeLanguage, $this->_languageWhitelist);
                    if ($isAllowed) {
                        $language = $localeLanguage;
                    }
                } catch (Exception $e) {
                }
            }

            $this->_initLocaleAndTranslate($serviceManager, $language);
        }
    }

    private function _detectUserLanguage()
    {
        $result = null;

        $auth = Zend_Auth::getInstance();
        if ($auth->hasIdentity()) {

            $userTable = new Users();

            $user = $userTable->find($auth->getIdentity())->current();

            if ($user) {
                $isAllowed = in_array($user->language, $this->_languageWhitelist);
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
    private function _initLocaleAndTranslate($serviceManager, $language)
    {
        // Locale
        $locale = new Zend_Locale($language);

        // Translation
        $translate = new Zend_Translate('Array', APPLICATION_PATH . '/languages', null, array(
            'scan'            => Zend_Translate::LOCALE_FILENAME,
            'disableNotices'  => true,
            'logUntranslated' => false,
            'locale'          => $locale,
        ));

        $translate->addTranslation(array(
            'content' => APPLICATION_PATH . '/../vendor/zendframework/zendframework1/resources/languages/',
            'scan'    => Zend_Translate::LOCALE_DIRECTORY,
            'locale'  => $locale,
        ));
        $translate->setLocale($locale);

        // populate for wide-engine
        Zend_Registry::set('Zend_Translate', $translate);
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

            $isAllowed = in_array($hostname, $this->_hostnameWhitelist);

            if (!$isAllowed) {
                $request->setDispatched(true);

                $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $this->_defaultHostname . $request->getRequestUri();

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
                $token = $request->getCookie('remember');
                if ($token) {
                    $adapter = new Project_Auth_Adapter_Remember();
                    $adapter->setCredential($token);
                    $auth = Zend_Auth::getInstance();
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
                if ($request->getHttpHost() != $host) {
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
                    $acl = $serviceManager->get(Acl::class);
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
