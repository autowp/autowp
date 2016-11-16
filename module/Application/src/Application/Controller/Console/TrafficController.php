<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Service\TrafficControl;

use Application\Model\Referer;

class TrafficController extends AbstractActionController
{

    public function autobanAction()
    {
        $service = new TrafficControl();
        $service->autoBan();

        Console::getInstance()->writeLine("done");
    }

    public function googleAction()
    {
        $service = new TrafficControl();

        $service->autoWhitelist();

        Console::getInstance()->writeLine("done");
    }

    public function gcAction()
    {
        $service = new TrafficControl();
        $count = $service->gc();

        Console::getInstance()->writeLine(sprintf("%d ip monitoring and banned ip rows was deleted", $count));
    }

    public function clearRefererMonitoringAction()
    {
        $table = new Referer();

        $count = $table->delete([
            'last_date < DATE_SUB(NOW(), INTERVAL 1 DAY)'
        ]);

        Console::getInstance()->writeLine(sprintf("%d referer monitoring rows was deleted", $count));
    }
}
