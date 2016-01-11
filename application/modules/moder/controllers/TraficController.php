<?php

class Moder_TraficController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder')) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {
        $service = new Application_Service_TrafficControl();

        if ($this->getRequest()->isPost()) {
            $user = $this->_helper->user()->get();

            $canBan = $this->_helper->user()->isAllowed('user', 'ban');
            if (!$canBan) {
                return $this->_forward('forbidden', 'error', 'default');
            }

            $service->ban($this->getParam('ip'), 10*24*3600, $user->id, '');

            return $this->_redirect($this->_helper->url->url(array(
                'ip' => null
            )));
        }

        $data = $service->getTopData();

        $users = new Users();

        foreach ($data as &$row) {
            $row['users'] = $users->fetchAll(array(
                'last_ip = inet_aton(inet6_ntoa(?))' => $row['ip']
            ));

            if ($row['ban']) {
                $row['ban']['user'] = null;
                if ($row['ban']['by_user_id']) {
                    $row['ban']['user'] = $users->find($row['ban']['by_user_id'])->current();
                }
            }

            $row['ban_url'] = $this->_helper->url->url(array(
                'ip' => $row['ip']
            ));
            $row['unban_url'] = $this->_helper->url->url(array(
                'action' => 'unban',
                'ip'     => $row['ip']
            ));
            $row['whitelist_url'] = $this->_helper->url->url(array(
                'action' => 'whitelist-add',
                'ip'     => $row['ip']
            ));
            $row['whois_url'] = 'http://nic.ru/whois/?query='.urlencode($row['ip']);
        }
        unset($row);

        $users = new Users();


        $this->view->rows = $data;
    }

    public function hostByAddrAction()
    {
        return $this->_helper->json(array(
            'host' => gethostbyaddr($this->getParam('ip'))
        ));
    }

    public function unbanAction()
    {
        $user = $this->_helper->user()->get();

        $canBan = $this->_helper->user()->isAllowed('user', 'ban');
        if (!$canBan) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $service = new Application_Service_TrafficControl();
        $service->unban($this->getParam('ip'));

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'index',
            'ip'     => null
        )));
    }

    public function whitelistAction()
    {
        $service = new Application_Service_TrafficControl();

        $data = $service->getWhitelistData();

        $users = new Users();

        foreach ($data as &$row) {
            $row['users'] = $users->fetchAll(array(
                'last_ip = INET_ATON(?)' => $row['ip']
            ));
            $row['delete_url'] = $this->_helper->url->url(array(
                'action' => 'whitelist-remove',
                'ip'     => $row['ip']
            ));
        }
        unset($row);

        $this->view->rows = $data;
    }

    public function whitelistRemoveAction()
    {
        $service = new Application_Service_TrafficControl();
        $service->deleteFromWhitelist($this->getParam('ip'));

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'whitelist',
            'ip'     => null
        )));
    }

    public function whitelistAddAction()
    {
        $ip = trim($this->getParam('ip'));

        if ($ip) {
            $service = new Application_Service_TrafficControl();
            $service->addToWhitelist($ip, 'manual click');
        }

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'index',
            'ip'     => null
        )));
    }
}