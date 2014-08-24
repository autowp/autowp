<?php

/** Zend_Controller_Plugin_Abstract */
require_once 'Zend/Controller/Plugin/Abstract.php';

class Project_Controller_Plugin_Profiler extends Zend_Controller_Plugin_Abstract
{
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        $auth = Zend_Auth::getInstance();
        if ($auth->hasIdentity()) {
            if ($auth->getIdentity() == 1) {
                $db = Zend_Controller_Front::getInstance()
                    ->getParam('bootstrap')
                    ->bootstrap('db')
                    ->getResource('db');

                $db->setProfiler(array(
                    'enabled' => true,
                    'class'   => "Zend_Db_Profiler_Firebug"
                ));
            }
        }
    }

    public function dispatchLoopShutdown()
    {
        $auth = Zend_Auth::getInstance();
        if ($auth->hasIdentity()) {
            if ($auth->getIdentity() == 1) {
                //Zend_Wildfire_Channel_HttpHeaders::getInstance()->flush();
                /*$p = Zend_Controller_Front::getInstance()
                    ->getParam('bootstrap')
                    ->getResource('db')->getProfiler()->getQueryProfiles();
                print_r($p); exit;*/

                //$this->getResponse()->sendHeaders();
            }
        }
    }
}
