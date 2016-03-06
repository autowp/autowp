<?php
class MaintenanceController extends Zend_Controller_Action
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
        $this->getResponse()->setHttpResponseCode(503);

        $this->view->assign(array(
            'progress' => $this->getProgress()
        ));
    }

    public function progressAction()
    {
        return $this->_helper->json(array(
            'progress' => $this->getProgress()
        ));
    }
}