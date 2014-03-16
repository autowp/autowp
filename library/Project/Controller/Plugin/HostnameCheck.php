<?php

/**
 * @author Dima
 * @todo refactor to controller action
 *
 */
class Project_Controller_Plugin_HostnameCheck extends Zend_Controller_Plugin_Abstract
{
    /**
     * @var array
     */
    protected $_hostnameWhitelist = array('www.autowp.ru', 'ru.autowp.ru', 'en.autowp.ru');

    /**
     * @var string
     */
    protected $_defaultHostname = 'www.autowp.ru';

    /**
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     *
     * @todo scheme preserve
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
        $hostname = $request->getServer('HTTP_HOST');

        $isAllowed = in_array($hostname, $this->_hostnameWhitelist);

        if (!$isAllowed) {
            $request->setDispatched(true);

            $redirectUrl = $request->getScheme() . '://' .
                $this->_defaultHostname . $request->getRequestUri();

            $this->getResponse()->setRedirect($redirectUrl, 301);
        }
    }
}