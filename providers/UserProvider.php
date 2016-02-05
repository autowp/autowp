<?php

class UserProvider extends Zend_Tool_Project_Provider_Abstract
{
    private function _init()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION);

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile');

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance();

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db')
            ->bootstrap('users');

        return $zendApp;
    }

    public function restoreVotes()
    {
        $zendApp = $this->_init();

        $usersService = $zendApp->getBootstrap()->getResource('users');
        $usersService->restoreVotes();

        echo "User votes restored\n";
    }

    public function refreshVoteLimits()
    {
        $zendApp = $this->_init();

        $usersService = $zendApp->getBootstrap()->getResource('users');
        $affected = $usersService->updateUsersVoteLimits();

        printf("Updated %s users\n", $affected);
    }
}