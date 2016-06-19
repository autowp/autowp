<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\CarOfDay;

class MidnightController extends AbstractActionController
{
    public function carOfDayAction()
    {
        $model = new CarOfDay();
        $model->pick();
    }
}