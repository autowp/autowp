<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initPhpEnvotiment()
    {
        setlocale(LC_ALL, 'en_US.UTF-8');
        setlocale(LC_CTYPE, "UTF8", "en_US.UTF-8");

        error_reporting(E_ALL);

        mb_internal_encoding("UTF-8");
        mb_regex_encoding("UTF-8");
        ini_set('iconv.internal_encoding', 'UTF-8');

        date_default_timezone_set('Europe/Moscow');
    }

    /**
     * @todo remove
     */
    protected function _initBackCompatibility()
    {
        $db = $this->bootstrap('db')->getResource('db');

        Zend_Registry::set('db', $db);

        define('APPLICATION_DIR', APPLICATION_PATH);
        define('PROJECT_DIR', '/home/autowp/autowp.ru');
        define('LIBRARY_DIR', PROJECT_DIR . '/library');
        define('RESOURCES_DIR', APPLICATION_PATH . '/resources');
        define('ROOT_FOLDER', PROJECT_DIR . '/'); // путь к корневой папке
        define('FOLDER', APPLICATION_PATH . '/'); // путь к public-папке
        define('IMAGES_DIR', PUBLIC_DIR . '/img');
        define('IMAGES_URL', '/img');
        define('CACHE_DIR', APPLICATION_DIR . '/cache');
        define('DOMAIN', 'autowp.ru');
        define('DOMAIN_WWW', 'www.autowp.ru');

        require_once 'Functions.php';
        require_once 'BBDocument.php';
        require_once 'Blocks.php';
    }

    protected function _initAutoloader()
    {
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
    }

    protected function _initPaginator()
    {
        Zend_Paginator::setDefaultScrollingStyle('Sliding');
        Zend_View_Helper_PaginationControl::setDefaultViewPartial('pagination_control.phtml');
    }

    protected function _initViewSetup()
    {
        $view = $this->bootstrap('view')->getResource('view');

        $view
            ->setEncoding('utf-8')
            ->strictVars(true);

        $view->headMeta()->appendHttpEquiv('Content-Type', 'text/html; charset=utf-8')
            ->appendName('keywords', 'auto, avto, автомобиль')
            ->appendName('description', 'Энциклопедия автомобилей в картинках. AutoWP.ru');
    }

    protected function _initLocaleAndTranslate()
    {
        $defaultLocale = 'en_US';
        $availLanguages = array('en', 'ru');

        $cachemanager = $this->bootstrap('cachemanager')->getResource('cachemanager');

        $longCache = $cachemanager->getCache('long');

        $localeCache = $cachemanager->getCache('locale');

        Zend_Locale_Data::setCache($localeCache);
        Zend_Date::setOptions(array('cache' => $localeCache));

        // Locale
        try {
            $locale = new Zend_Locale(Zend_Locale::BROWSER);
        } catch (Exception $e) {
            $locale = new Zend_Locale($defaultLocale);
        }

        if (!in_array($locale->getLanguage(), $availLanguages)) {
            // when user requests a not available language reroute to default
            $locale->setLocale($defaultLocale);
        }

        // Translation
        Zend_Translate::setCache($longCache);
        $translate = new Zend_Translate('Array', APPLICATION_PATH . '/languages', null, array(
            'scan'            => Zend_Translate::LOCALE_FILENAME,
            'disableNotices'  => true,
            'logUntranslated' => false,
            'locale'          => $locale,
        ));

        $translate->addTranslation(array(
            'content' => PROJECT_DIR . '/vendor/zendframework/ZendFramework/resources/languages/',
            'scan'    => Zend_Translate::LOCALE_DIRECTORY,
            'locale'  => $locale,
        ));
        $translate->setLocale($locale);

        // populate for wide-engine
        Zend_Registry::set('Zend_Translate', $translate);
        Zend_Registry::set('Zend_Locale', $locale);
    }

    protected function _initMail()
    {
        $options = $this->getOption('mail');
        $host = $options['host'];
        $params = $options;
        unset($params['host']);

        $transport = new Zend_Mail_Transport_Smtp($options['host'], $params);
        Zend_Mail::setDefaultTransport($transport);
    }
}