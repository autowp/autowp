<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

use Application\Model\Referer;

class HotlinksController extends AbstractRestfulController
{
    public function hostsAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'view')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $refererTable = new Referer();
        $rows = $refererTable->getAdapter()->fetchAll(
            $refererTable->getAdapter()->select()
                ->from($refererTable->info('name'), ['host', 'c' => 'SUM(count)'])
                ->where('last_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)')
                ->group('host')
                ->order('c desc')
                ->limit(100)
        );

        $whitelistTable = new Referer\Whitelist();
        $blacklistTable = new Referer\Blacklist();

        $db = $refererTable->getAdapter();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'host'        => $row['host'],
                'count'       => (int)$row['c'],
                'whitelisted' => $whitelistTable->containsHost($row['host']),
                'blacklisted' => $blacklistTable->containsHost($row['host']),
                'links'       => $db->fetchAll(
                    $db->select()
                        ->from($refererTable->info('name'))
                        ->where('host = ?', (string)$row['host'])
                        ->where('last_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)')
                        ->order('count desc')
                        ->limit(20)
                )
            ];
        }

        return new JsonModel([
            'items' => $items
        ]);
    }

    public function hostsDeleteAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'manage')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $refererTable = new Referer();

        $refererTable->delete([]);

        return $this->getResponse()->setStatusCode(204);
    }

    public function hostDeleteAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'manage')) {
            return new ApiProblemResponse(new ApiProblem(403, 'Forbidden'));
        }

        $refererTable = new Referer();

        $refererTable->delete([
            'host = ?' => (string)$this->params('host')
        ]);

        return $this->getResponse()->setStatusCode(204);
    }

    public function whitelistPostAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $host = isset($data['host']) ? (string)$data['host'] : null;

        if (! $host) {
            return new ApiProblemResponse(new ApiProblem(400, 'Validation error'));
        }

        $blacklistTable = new Referer\Blacklist();
        $whitelistTable = new Referer\Whitelist();

        $blacklistTable->delete([
            'host = ?' => $host
        ]);

        $whitelistTable->insert([
            'host' => $host
        ]);

        return $this->getResponse()->setStatusCode(201);
    }

    public function blacklistPostAction()
    {
        if (! $this->user()->isAllowed('hotlinks', 'manage')) {
            return $this->forbiddenAction();
        }

        $data = $this->processBodyContent($this->getRequest());

        $host = isset($data['host']) ? (string)$data['host'] : null;

        if (! $host) {
            return new ApiProblemResponse(new ApiProblem(400, 'Validation error'));
        }

        $blacklistTable = new Referer\Blacklist();
        $whitelistTable = new Referer\Whitelist();

        $whitelistTable->delete([
            'host = ?' => $host
        ]);

        $blacklistTable->insert([
            'host' => $host
        ]);

        return $this->getResponse()->setStatusCode(201);
    }
}
