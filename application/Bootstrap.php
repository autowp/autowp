<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    private $_cookieDomains = array(
        'wheelsage.org'     => '.wheelsage.org',
        'fr.wheelsage.org'  => '.wheelsage.org',
        'en.wheelsage.org'  => '.wheelsage.org',
        'zh.wheelsage.org'  => '.wheelsage.org',
        'www.wheelsage.org' => '.wheelsage.org',
        'en.autowp.ru'      => '.autowp.ru',
        'autowp.ru'         => '.autowp.ru',
        'www.autowp.ru'     => '.autowp.ru',
        'ru.autowp.ru'      => '.autowp.ru'
    );

    protected function _initPhpEnvoriment()
    {
        setlocale(LC_ALL, 'en_US.UTF-8');
        setlocale(LC_CTYPE, "UTF8", "en_US.UTF-8");

        if (isset($_SERVER['HTTP_HOST'])) {
            $hostname = $_SERVER['HTTP_HOST'];
            if (isset($this->_cookieDomains[$hostname])) {
                Zend_Registry::set('cookie_domain', $this->_cookieDomains[$hostname]);
                Zend_Session::setOptions(array(
                    'cookie_domain' => $this->_cookieDomains[$hostname]
                ));
            }
        }
    }

    /**
     * @todo remove
     */
    protected function _initBackCompatibility()
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
    }

    protected function _initAutoloader()
    {
        require_once realpath(APPLICATION_PATH . '/../vendor/autoload.php');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
    }

    protected function _initPaginator()
    {
        Zend_Paginator::setDefaultScrollingStyle('Sliding');
        Zend_View_Helper_PaginationControl::setDefaultViewPartial('pagination_control.phtml');
    }

    protected function _initLocaleAndTranslate()
    {
        $cachemanager = $this->bootstrap('cachemanager')->getResource('cachemanager');

        $longCache = $cachemanager->getCache('long');
        $localeCache = $cachemanager->getCache('locale');

        Zend_Locale_Data::setCache($localeCache);
        Zend_Date::setOptions(array('cache' => $localeCache));
        //Zend_Translate::setCache($longCache);
    }

}