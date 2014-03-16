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
            ->bootstrap('db');

        $bannedIp = new Banned_Ip();
        $bannedIp->autoBan();
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

        $imTable = new Ip_Monitoring();
        $whitelistTable = new Ip_Whitelist();
        $bannedIp = new Banned_Ip();

        $sql =  'SELECT ip, SUM(count) AS count FROM ip_monitoring4 '.
                'WHERE day_date=CURDATE() GROUP BY ip '.
                'ORDER BY count DESC limit 1000';
        $rows = $imTable->getAdapter()->fetchAll($sql);

        foreach ($rows as &$row) {
            $ip = $row['ip'];

            print $ip. ': ';


            if (false && $whitelistTable->find($ip)->current()) {
                print 'whitelist, skip';
            } else {

                $whitelist = false;
                $whitelistDesc = '';

                $host = gethostbyaddr($ip);

                if ($host === false) {
                    $host = 'unknown.host';
                }

                print $host;

                $msnHost = 'msnbot-' . str_replace('.', '-', $ip) . '.search.msn.com';
                $yandexComHost = 'spider-'.str_replace('.', '-', $ip).'.yandex.com';
                $mailHostPattern = '/^fetcher[0-9]-[0-9]\.p\.mail.ru$/';
                $googlebotHost = 'crawl-' . str_replace('.', '-', $ip) . '.googlebot.com';
                if ($host == $msnHost) {
                    $whitelist = true;
                    $whitelistDesc = 'msnbot autodetect';
                } if ($host == $yandexComHost) {
                    $whitelist = true;
                    $whitelistDesc = 'yandex.com autodetect';
                } if ($host == $googlebotHost) {
                    $whitelist = true;
                    $whitelistDesc = 'googlebot autodetect';
                } if (preg_match($mailHostPattern, $host)) {
                    $whitelist = true;
                    $whitelistDesc = 'mail.ru autodetect';
                }


                if ($whitelist) {
                    $wr = $whitelistTable->fetchRow(array(
                        'ip = ?' => $ip
                    ));
                    if (!$wr) {
                        $whitelistTable->insert(array(
                            'ip'          => $ip,
                            'description' => $whitelistDesc
                        ));
                    }

                    $banRow = $bannedIp->fetchRow(array(
                        'ip = ?' => ip2long($ip)
                    ));

                    if ($banRow) {
                        $banRow->delete();
                    }

                    $imTable->delete(array(
                        'ip = ?' => $ip
                    ));

                    print ' whitelisted';
                }
            }

            print PHP_EOL;
        }
    }
}