<?php

/** Zend_Controller_Plugin_Abstract */
require_once 'Zend/Controller/Plugin/Abstract.php';

class Project_Controller_Plugin_Traffic extends Zend_Controller_Plugin_Abstract
{
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        $unlimitedTraffic = Zend_Controller_Action_HelperBroker::getStaticHelper('user')
            ->direct()->isAllowed('website', 'unlimited-traffic');

        $ip = $request->getServer('REMOTE_ADDR');

        $ban = new Banned_Ip();
        $banInfo = $ban->getBanInfo($ip);
        if ($banInfo) {
            header('HTTP/1.1 509 Bandwidth Limit Exceeded');
            print 'Access denied: ' . $banInfo['reason'];
            exit;
        }

        if (!$unlimitedTraffic) {
            if (!$ban->inWhiteList($ip)) {
                $monitoring = new Ip_Monitoring();
                $monitoring->pushHit($ip);
            }
        }
    }
}
