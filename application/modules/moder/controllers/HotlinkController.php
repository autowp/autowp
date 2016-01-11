<?php

class Moder_HotlinkController extends Zend_Controller_Action
{
    public function indexAction()
    {
        if (!$this->_helper->user()->isAllowed('hotlinks', 'view')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $refererTable = new Referer();
        $hosts = $refererTable->getAdapter()->fetchAll(
            $refererTable->getAdapter()->select()
                ->from($refererTable->info('name'), array('host', 'c' => 'SUM(count)'))
                ->where('last_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)')
                ->group('host')
                ->order('c desc')
                ->limit(100)
        );

        $whitelistTable = new Referer_Whitelist();
        $blacklistTable = new Referer_Blacklist();

        foreach ($hosts as &$host) {

            $host['whitelisted'] = $whitelistTable->containsHost($host['host']);
            $host['blacklisted'] = $blacklistTable->containsHost($host['host']);
            $host['links'] = $refererTable->fetchAll(
                $refererTable->select(true)
                    ->where('host = ?', (string)$host['host'])
                    ->where('last_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)')
                    ->order('count desc')
                    ->limit(20)
            );
        }

        $this->view->assign(array(
            'hosts'     => $hosts,
            'canManage' => $this->_helper->user()->isAllowed('hotlinks', 'manage')
        ));
    }

    public function clearAllAction()
    {
        if (!$this->_helper->user()->isAllowed('hotlinks', 'manage')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $refererTable = new Referer();

        $refererTable->delete(array());

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'index',
        )));
    }

    public function clearHostAction()
    {
        if (!$this->_helper->user()->isAllowed('hotlinks', 'manage')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $refererTable = new Referer();

        $refererTable->delete(array(
            'host = ?' => (string)$this->_getParam('host')
        ));

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'index',
            'host'   => null
        )));
    }

    public function whitelistHostAction()
    {
        if (!$this->_helper->user()->isAllowed('hotlinks', 'manage')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $host = $this->_getParam('host');

        if ($host) {
            $whitelistTable = new Referer_Whitelist();

            $whitelistTable->insert(array(
                'host' => (string)$host
            ));
        }

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'index',
            'host'   => null
        )));
    }

    public function whitelistAndClearHostAction()
    {
        if (!$this->_helper->user()->isAllowed('hotlinks', 'manage')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $host = (string)$this->_getParam('host');

        if ($host) {
            $whitelistTable = new Referer_Whitelist();

            $whitelistTable->insert(array(
                'host' => $host
            ));

            $refererTable = new Referer();
            $refererTable->delete(array(
                'host = ?' => $host
            ));
        }

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'index',
            'host'   => null
        )));
    }

    public function blacklistHostAction()
    {
        if (!$this->_helper->user()->isAllowed('hotlinks', 'manage')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $host = $this->_getParam('host');

        if ($host) {
            $blacklistTable = new Referer_Blacklist();

            $blacklistTable->insert(array(
                'host' => (string)$host
            ));
        }

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'index',
            'host'   => null
        )));
    }
}