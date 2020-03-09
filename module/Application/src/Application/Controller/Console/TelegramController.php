<?php

namespace Application\Controller\Console;

use Application\Service\TelegramService;
use Laminas\Mvc\Controller\AbstractActionController;

class TelegramController extends AbstractActionController
{
    private TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    public function registerAction()
    {
        $this->telegram->registerWebhook();

        return "done\n";
    }

    public function notifyInboxAction()
    {
        $this->telegram->notifyInbox($this->params('picture_id'));

        return "done\n";
    }
}
