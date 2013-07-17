<?php

/**
 * @author Dima
 * @todo refactor to controller action
 *
 */
class Project_Controller_Plugin_AuthRemember extends Zend_Controller_Plugin_Abstract
{
    /**
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
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