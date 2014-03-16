<?php

/**
 * @author Dima
 */
class Project_Controller_Plugin_Maintenance extends Zend_Controller_Plugin_Abstract
{
    /**
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
        /*if ($request->getControllerName() != 'maintenance') {
            $request
                ->setControllerName('maintenance')
                ->setActionName('index');
        }*/



        if ($request->getModuleName() == 'forums') {

            $allow = false;

            $auth = Zend_Auth::getInstance();
            if ($auth->hasIdentity()) {
                $allow = $auth->getIdentity() == 1;
            }

            if (!$allow) {

                if ($request->getControllerName() != 'maintenance') {
                    $request
                        ->setControllerName('maintenance')
                        ->setActionName('index');
                }
            }
        }
    }
}