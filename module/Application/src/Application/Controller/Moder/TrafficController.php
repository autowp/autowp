<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Service\TrafficControl;

use Users;

class TrafficController extends AbstractActionController
{
    public function indexAction()
    {
        if (!$this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $service = new TrafficControl();

        $data = $service->getTopData();

        $users = new Users();

        foreach ($data as &$row) {
            $row['users'] = $users->fetchAll([
                'last_ip = inet_aton(inet6_ntoa(?))' => $row['ip']
            ]);

            if ($row['ban']) {
                $row['ban']['user'] = null;
                if ($row['ban']['by_user_id']) {
                    $row['ban']['user'] = $users->find($row['ban']['by_user_id'])->current();
                }
            }

            $row['whoisUrl'] = 'http://nic.ru/whois/?query='.urlencode($row['ip']);
            $row['banUrl']   = $this->url()->fromRoute('ban/ban-ip', [
                'ip' => $row['ip']
            ]);
            $row['unbanUrl']   = $this->url()->fromRoute('ban/unban-ip', [
                'ip' => $row['ip']
            ]);
        }
        unset($row);

        $users = new Users();


        return [
            'rows'         => $data,
            'whitelistUrl' => $this->url()->fromRoute('moder/traffic/whitelist-add'),
        ];
    }

    public function hostByAddrAction()
    {
        if (!$this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'host' => gethostbyaddr($this->params()->fromQuery('ip'))
        ]);
    }

    public function whitelistAction()
    {
        if (!$this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $service = new TrafficControl();

        $data = $service->getWhitelistData();

        $users = new Users();

        foreach ($data as &$row) {
            $row['users'] = []; /*$users->fetchAll([
                'last_ip = INET_ATON(?)' => $row['ip']
            ]);*/
        }
        unset($row);

        return [
            'rows'      => $data,
            'deleteUrl' => $this->url()->fromRoute('moder/traffic/whitelist-remove')
        ];
    }

    public function whitelistRemoveAction()
    {
        if (!$this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $service = new TrafficControl();
        $service->deleteFromWhitelist($this->params()->fromPost('ip'));

        return $this->redirect()->toUrl($this->url()->fromRoute('moder/traffic/whitelist'));
    }

    public function whitelistAddAction()
    {
        if (!$this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $ip = trim($this->params()->fromPost('ip'));

        if ($ip) {
            $service = new TrafficControl();
            $service->addToWhitelist($ip, 'manual click');
        }

        return $this->redirect()->toUrl($this->url()->fromRoute('moder/traffic'));
    }
}
