<?php

class TelegramProvider extends Zend_Tool_Project_Provider_Abstract
{
    private function _init()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml
        /* @var $zendApp Zend_Application */
        $zendApp = $this->_loadedProfile->search('BootstrapFile')->getApplicationInstance();

        $zendApp
            ->bootstrap('backCompatibility')
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db')
            ->bootstrap('router')
            ->bootstrap('telegram');

        Zend_Controller_Front::getInstance()
            ->setParam('bootstrap', $zendApp->getBootstrap());

        return $zendApp;
    }

    public function register()
    {
        $zendApp = $this->_init();

        $telegram = $zendApp->getBootstrap()->getResource('telegram');
        $telegram->registerWebhook();
    }

    public function notifyInbox($id)
    {
        $zendApp = $this->_init();

        $telegram = $zendApp->getBootstrap()->getResource('telegram');
        $telegram->notifyInbox($id);
    }
}