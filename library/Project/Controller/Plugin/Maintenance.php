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
        if ($request->getControllerName() != 'maintenance') {
            $request
                ->setControllerName('maintenance')
                ->setActionName('index');
        }
    }
}