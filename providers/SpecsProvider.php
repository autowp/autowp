<?php

require_once 'Zend/Tool/Project/Provider/Abstract.php';
require_once 'Zend/Tool/Project/Provider/Exception.php';

class SpecsProvider extends Zend_Tool_Project_Provider_Abstract
{

    private function _init()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        return $zendApp
            ->bootstrap('backCompatibility')
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');

    }

    public function refreshConflictFlags()
    {
        $zendApp = $this->_init();

        $service = new Application_Service_Specifications();

        $service->refreshConflictFlags();
    }

    public function refreshUsersStat()
    {
        $zendApp = $this->_init();

        $service = new Application_Service_Specifications();

        $service->refreshUsersConflictsStat();
    }

    public function refreshItemConflictFlags($typeId, $itemId)
    {
        $zendApp = $this->_init();

        $service = new Application_Service_Specifications();

        $service->refreshItemConflictFlags($typeId, $itemId);
    }


    public function refreshUserStat($userId)
    {
        $zendApp = $this->_init();

        $service = new Application_Service_Specifications();

        $service->refreshUserConflicts($userId);
    }
}