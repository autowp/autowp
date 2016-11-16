<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Message;

class MessageController extends AbstractActionController
{
    /**
     * @var Message
     */
    private $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function clearOldSystemPMAction()
    {
        $count = $this->message->recycleSystem();

        Console::getInstance()->writeLine(sprintf("%d messages was deleted", $count));
    }

    public function clearDeletedPMAction()
    {
        $count = $this->message->recycle();

        Console::getInstance()->writeLine(sprintf("%d messages was deleted", $count));
    }
}
