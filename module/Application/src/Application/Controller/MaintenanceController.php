<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

class MaintenanceController extends AbstractRestfulController
{
    private function getProgress()
    {
        exec('cat /proc/mdstat', $out);

        $total = null;
        $current = null;
        $progress = null;

        foreach ($out as $line) {
            if (preg_match('|\(([0-9]+)/([0-9]+)\)|isu', $line, $match)) {
                $total = (float)$match[2];
                $current = (float)$match[1];
            }
        }

        if ($total && $current) {
            $progress = $current / $total * 100;
        }

        return $progress;
    }

    public function indexAction()
    {
        $this->getResponse()->setStatusCode(503);

        return [
            'progress' => $this->getProgress()
        ];
    }

    public function progressAction()
    {
        return new JsonModel([
            'progress' => $this->getProgress()
        ]);
    }
}
