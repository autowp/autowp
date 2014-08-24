<?php

/**
 * @author Dima
 * @todo refactor to controller action
 *
 */
class Project_Controller_Plugin_Language extends Zend_Controller_Plugin_Abstract
{
    /**
     * @var array
     */
    protected $_languageWhitelist = array('en', 'ru');

    protected $_skipHostname = array('i.wheelsage.org');

    protected $_skipAction = array(
        'default' => array(
            'picture-file' => array(
                'index'
            )
        )
    );

    /**
     * @var string
     */
    protected $_defaultLanguage = 'en';

    /**
     * @var string
     */
    protected $_hostname = '.autowp.ru';

    /**
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     *
     * @todo scheme preserve
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
        $language = $this->_defaultLanguage;

        $hostname = $request->getServer('HTTP_HOST');

        if (in_array($hostname, $this->_skipHostname)) {
            $controller = $request->getControllerName();
            $module = $request->getModuleName();
            if ($module === null) {
                $module = 'default';
            }

            if (isset($this->_skipAction[$module][$controller])) {
                $action = $request->getActionName();

                if (in_array($action, $this->_skipAction[$module][$controller])) {
                    return;
                }
            }
        }

        $parts = explode('.', $hostname);
        if (count($parts) > 0) {
            $langPart = $parts[0];
            $isAllowed = in_array($langPart, $this->_languageWhitelist);

            if (!$isAllowed) {

                try {
                    $locale = new Zend_Locale(Zend_Locale::BROWSER);
                    $localeLanguage = $locale->getLanguage();
                    $isAllowed = in_array($localeLanguage, $this->_languageWhitelist);
                    if ($isAllowed) {
                        $language = $localeLanguage;
                    }
                } catch (Exception $e) {
                }

                $request->setDispatched(true);

                $redirectUrl = $request->getScheme() . '://' .
                    $language . $this->_hostname . $request->getRequestUri();

                $this->getResponse()->setRedirect($redirectUrl, 301);
            } else {
                $language = $langPart;
            }
        }

        $this->_initLocaleAndTranslate($language);
    }

    /**
     * @param string $language
     */
    protected function _initLocaleAndTranslate($language)
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
            'content' => PROJECT_DIR . '/vendor/zendframework/zf1/resources/languages/',
            'scan'    => Zend_Translate::LOCALE_DIRECTORY,
            'locale'  => $locale,
        ));
        $translate->setLocale($locale);

        // populate for wide-engine
        Zend_Registry::set('Zend_Translate', $translate);
        Zend_Registry::set('Zend_Locale', $locale);
    }
}