<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Referer;

class RefererController extends AbstractActionController
{
    public function clearRefererMonitoringAction()
    {
        $table = new Referer();

        $count = $table->delete([
            'last_date < DATE_SUB(NOW(), INTERVAL 1 DAY)'
        ]);

        Console::getInstance()->writeLine(sprintf("%d referer monitoring rows was deleted", $count));
    }
}
