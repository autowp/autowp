<?php

namespace Autowp\Message\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Message\MessageService;

class ConsoleController extends AbstractActionController
{
    /**
     * @var MessageService
     */
    private $message;

    public function __construct(MessageService $message)
    {
        $this->message = $message;
    }

    public function clearOldSystemPMAction()
    {
        $count = $this->message->recycleSystem();

        return sprintf("%d messages was deleted\n", $count);
    }

    public function clearDeletedPMAction()
    {
        $count = $this->message->recycle();

        return sprintf("%d messages was deleted\n", $count);
    }
}
