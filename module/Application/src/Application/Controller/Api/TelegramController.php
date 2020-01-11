<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Application\Service\TelegramService;

class TelegramController extends AbstractActionController
{
    /**
     * @var TelegramService
     */
    private $service;

    public function __construct(TelegramService $service)
    {
        $this->service = $service;
    }

    public function webhookAction()
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if (! $this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        if (! $this->service->checkTokenMatch($this->params('token'))) {
            return $this->forbiddenAction();
        }

        $this->service->commandsHandler(true);

        return new JsonModel([
            'status' => true
        ]);
    }
}
