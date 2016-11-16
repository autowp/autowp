<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Service\TelegramService;

class TelegramController extends AbstractActionController
{
    /**
     * @var TelegramService
     */
    private $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    public function registerAction()
    {
        $this->telegram->registerWebhook();

        Console::getInstance()->writeLine("done");
    }

    public function notifyInboxAction()
    {
        $this->telegram->notifyInbox($this->params('picture_id'));

        Console::getInstance()->writeLine("done");
    }
}
