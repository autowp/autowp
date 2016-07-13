<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;

use Referer;
use Referer_Blacklist;
use Referer_Whitelist;

class HotlinkController extends AbstractActionController
{
    public function indexAction()
    {
        if (!$this->user()->isAllowed('hotlinks', 'view')) {
            return $this->forbiddenAction();
        }

        $refererTable = new Referer();
        $hosts = $refererTable->getAdapter()->fetchAll(
            $refererTable->getAdapter()->select()
                ->from($refererTable->info('name'), ['host', 'c' => 'SUM(count)'])
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

        return [
            'hosts'     => $hosts,
            'canManage' => $this->user()->isAllowed('hotlinks', 'manage')
        ];
    }

    public function clearAllAction()
    {
        if (!$this->user()->isAllowed('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $refererTable = new Referer();

        $refererTable->delete([]);

        return $this->redirect()->toUrl($this->url()->fromRoute('moder/hotlink'));
    }

    public function clearHostAction()
    {
        if (!$this->user()->isAllowed('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $refererTable = new Referer();

        $refererTable->delete([
            'host = ?' => (string)$this->params()->fromPost('host')
        ]);

        return $this->redirect()->toUrl($this->url()->fromRoute('moder/hotlink'));
    }

    public function whitelistHostAction()
    {
        if (!$this->user()->isAllowed('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $host = $this->params()->fromPost('host');

        if ($host) {
            $whitelistTable = new Referer_Whitelist();

            $whitelistTable->insert([
                'host' => (string)$host
            ]);
        }

        return $this->redirect()->toUrl($this->url()->fromRoute('moder/hotlink'));
    }

    public function whitelistAndClearHostAction()
    {
        if (!$this->user()->isAllowed('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $host = (string)$this->params()->fromPost('host');

        if ($host) {
            $whitelistTable = new Referer_Whitelist();

            $whitelistTable->insert([
                'host' => $host
            ]);

            $refererTable = new Referer();
            $refererTable->delete([
                'host = ?' => $host
            ]);
        }

        return $this->redirect()->toUrl($this->url()->fromRoute('moder/hotlink'));
    }

    public function blacklistHostAction()
    {
        if (!$this->user()->isAllowed('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $host = $this->params()->fromPost('host');

        if ($host) {
            $blacklistTable = new Referer_Blacklist();

            $blacklistTable->insert([
                'host' => (string)$host
            ]);
        }

        return $this->redirect()->toUrl($this->url()->fromRoute('moder/hotlink'));
    }
}