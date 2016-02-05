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
    private $_userDetectable = array(
        'wheelsage.org'
    );

    /**
     * @var array
     */
    private $_skipHostname = array('i.wheelsage.org');

    /**
     * @var array
     */
    private $_skipAction = array(
        'default' => array(
            'picture-file' => array(
                'index'
            )
        )
    );

    /**
     * @var string
     */
    private $_defaultLanguage = 'en';

    /**
     * @var string
     */
    private $_hostname = '.autowp.ru';

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
        
        if (in_array($hostname, $this->_userDetectable)) {
            $userLanguage = $this->_detectUserLanguage();
            
            $hosts = Zend_Controller_Front::getInstance()
                ->getParam('bootstrap')->getOption('hosts');
            
            if (isset($hosts[$userLanguage])) {
                $redirectUrl = $request->getScheme() . '://' .
                    $hosts[$userLanguage]['hostname'] . $request->getRequestUri();
            
                $this->getResponse()->setRedirect($redirectUrl);
                return;
            }
        }

        if (isset($this->_redirects[$hostname])) {
            $request->setDispatched(true);

            $redirectUrl = $request->getScheme() . '://' .
                $this->_redirects[$hostname] . $request->getRequestUri();

            $this->getResponse()->setRedirect($redirectUrl, 301);
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

        /*$parts = explode('.', $hostname);
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
        }*/

        $this->_initLocaleAndTranslate($language);
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
    private function _initLocaleAndTranslate($language)
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
            'content' => PROJECT_DIR . '/vendor/zendframework/zendframework1/resources/languages/',
            'scan'    => Zend_Translate::LOCALE_DIRECTORY,
            'locale'  => $locale,
        ));
        $translate->setLocale($locale);

        // populate for wide-engine
        Zend_Registry::set('Zend_Translate', $translate);
        Zend_Registry::set('Zend_Locale', $locale);
    }
}