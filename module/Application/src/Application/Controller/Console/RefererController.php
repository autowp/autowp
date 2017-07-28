<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Referer;

class RefererController extends AbstractActionController
{
    /**
     * @var Referer
     */
    private $referer;

    public function __construct(Referer $referer)
    {
        $this->referer = $referer;
    }

    public function clearRefererMonitoringAction()
    {
        $count = $this->referer->garbageCollect();

        return sprintf("%d referer monitoring rows was deleted\n", $count);
    }
}
