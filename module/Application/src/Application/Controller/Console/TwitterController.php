<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\CarOfDay;

class TwitterController extends AbstractActionController
{
    /**
     * @var array
     */
    private $twitterConfig;

    /**
     * @var CarOfDay
     */
    private $carOfDay;

    public function __construct(array $twitterConfig, CarOfDay $carOfDay)
    {
        $this->twitterConfig = $twitterConfig;
        $this->carOfDay = $carOfDay;
    }

    public function carOfDayAction()
    {
        $this->carOfDay->putCurrentToTwitter($this->twitterConfig);

        return "done\n";
    }
}
