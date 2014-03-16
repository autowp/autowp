<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initPhpEnvoriment()
    {
        setlocale(LC_ALL, 'en_US.UTF-8');
        setlocale(LC_CTYPE, "UTF8", "en_US.UTF-8");
    }

    /**
     * @todo remove
     */
    protected function _initBackCompatibility()
    {
        define('PUBLIC_DIR', realpath(APPLICATION_PATH . '/../public_html'));
        define('PROJECT_DIR', '/home/autowp/autowp.ru');
        define('RESOURCES_DIR', APPLICATION_PATH . '/resources');
        define('IMAGES_DIR', PUBLIC_DIR . '/img');
        define('IMAGES_URL', '/img');
        define('DOMAIN', 'autowp.ru');
        define('DOMAIN_WWW', 'www.autowp.ru');

        define('MYSQL_DATE', 'yyyy-MM-dd');
        define('MYSQL_TIME', 'HH:mm:ss');
        define('MYSQL_DATETIME', MYSQL_DATE . ' ' . MYSQL_TIME);

        require_once 'BBDocument.php';
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

    protected function _initLocaleAndTranslate()
    {
        $cachemanager = $this->bootstrap('cachemanager')->getResource('cachemanager');

        $longCache = $cachemanager->getCache('long');
        $localeCache = $cachemanager->getCache('locale');

        Zend_Locale_Data::setCache($localeCache);
        Zend_Date::setOptions(array('cache' => $localeCache));
        Zend_Translate::setCache($longCache);
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