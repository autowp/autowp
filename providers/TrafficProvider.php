<?php

require_once 'Zend/Tool/Project/Provider/Abstract.php';
require_once 'Zend/Tool/Project/Provider/Exception.php';

class TrafficProvider extends Zend_Tool_Project_Provider_Abstract
{

    public function autoban()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db')
            ->bootstrap('backCompatibility');

        $service = new Application_Service_TrafficControl();
        $service->autoBan();
    }

    public function google()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');

        $service = new Application_Service_TrafficControl();

        $service->autoWhitelist();
    }
}