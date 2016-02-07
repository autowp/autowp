<?php

use Application\Model\CarOfDay;

class MidnightProvider extends Zend_Tool_Project_Provider_Abstract
{
    public function carOfDay()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION);

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile');

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance();

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');

        $model = new CarOfDay();
        $model->pick();
    }
}