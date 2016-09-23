<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\CarOfDay;

class TwitterController extends AbstractActionController
{
    /**
     * @var array
     */
    private $twitterConfig;

    public function __construct(array $twitterConfig)
    {
        $this->twitterConfig = $twitterConfig;
    }

    public function carOfDayAction()
    {
        $model = new CarOfDay();
        $model->putCurrentToTwitter($this->twitterConfig);

        Console::getInstance()->writeLine("done");
    }
}