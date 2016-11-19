<?php

namespace Autowp\Traffic\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Autowp\Traffic\TrafficControl;

use Application\Model\DbTable\User;

class ModerController extends AbstractActionController
{
    /**
     * @var TrafficControl
     */
    private $service;

    public function __construct(TrafficControl $service)
    {
        $this->service = $service;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $data = $this->service->getTopData();

        $users = new User();

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

        $users = new User();


        return [
            'rows'         => $data,
            'whitelistUrl' => $this->url()->fromRoute('moder/traffic/whitelist-add'),
        ];
    }

    public function hostByAddrAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'host' => gethostbyaddr($this->params()->fromQuery('ip'))
        ]);
    }

    public function whitelistAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $data = $this->service->getWhitelistData();

        $users = new User();

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
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->service->deleteFromWhitelist($this->params()->fromPost('ip'));

        return $this->redirect()->toUrl($this->url()->fromRoute('moder/traffic/whitelist'));
    }

    public function whitelistAddAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $ip = trim($this->params()->fromPost('ip'));

        if ($ip) {
            $this->service->addToWhitelist($ip, 'manual click');
        }

        return $this->redirect()->toUrl($this->url()->fromRoute('moder/traffic'));
    }
}
