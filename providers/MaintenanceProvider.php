<?php

require_once 'Zend/Tool/Project/Provider/Abstract.php';
require_once 'Zend/Tool/Project/Provider/Exception.php';

class MaintenanceProvider extends Zend_Tool_Project_Provider_Abstract
{

    public function dump()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $db = $zendApp->getBootstrap()->bootstrap('db')->getResource('db');

        $config = $db->getConfig();

        $destFile = APPLICATION_PATH . '/data/dump/' . date('Y-m-d_H.i.s') . '.dump.sql';

        print 'Dumping ... ';

        $cmd = sprintf(
            'mysqldump -u%s -p%s -h%s %s > %s',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['dbname']),
            escapeshellarg($destFile)
        );

        exec($cmd);

        if (!file_exists($destFile)) {
            throw new Exception('Error creating dump');
        }

        print 'ok' . PHP_EOL;

        print 'Gzipping ... ';

        $cmd = sprintf(
            'gzip %s',
            escapeshellarg($destFile)
        );

        exec($cmd);

        print 'ok' . PHP_EOL;
    }


}