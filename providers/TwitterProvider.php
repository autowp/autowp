<?php

use Application\Model\CarOfDay;

class TwitterProvider extends Zend_Tool_Project_Provider_Abstract
{
    public function carOfDay()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');

        $options = $zendApp->getOptions();
        $twOptions = $options['twitter'];

        $model = new CarOfDay();
        $model->putCurrentToTwitter($twOptions);
    }
}