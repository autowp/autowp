<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Message;

class MessageController extends AbstractActionController
{
    public function clearOldSystemPMAction()
    {
        $mModel = new Message();
        $count = $mModel->recycleSystem();

        Console::getInstance()->writeLine(sprintf("%d messages was deleted", $count));
    }

    public function clearDeletedPMAction()
    {
        $mModel = new Message();
        $count = $mModel->recycle();

        Console::getInstance()->writeLine(sprintf("%d messages was deleted", $count));
    }
}