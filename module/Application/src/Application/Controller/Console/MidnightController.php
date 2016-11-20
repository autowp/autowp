<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\CarOfDay;

class MidnightController extends AbstractActionController
{
    /**
     * @var CarOfDay
     */
    private $carOfDay;

    public function __construct(CarOfDay $carOfDay)
    {
        $this->carOfDay = $carOfDay;
    }

    public function carOfDayAction()
    {
        $this->carOfDay->pick();
    }
}
