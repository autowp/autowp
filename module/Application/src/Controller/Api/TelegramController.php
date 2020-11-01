<?php

namespace Application\Controller\Api;

use Application\Service\TelegramService;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Telegram\Bot\Exceptions\TelegramSDKException;

/**
 * @method ViewModel forbiddenAction()
 */
class TelegramController extends AbstractActionController
{
    private TelegramService $service;

    public function __construct(TelegramService $service)
    {
        $this->service = $service;
    }

    /**
     * @throws TelegramSDKException
     */
    public function webhookAction(): ViewModel
    {
        /** @var Request $request */
        $request = $this->getRequest();
        if (! $request->isPost()) {
            return $this->forbiddenAction();
        }

        if (! $this->service->checkTokenMatch((string) $this->params('token'))) {
            return $this->forbiddenAction();
        }

        $this->service->commandsHandler(true);

        return new JsonModel([
            'status' => true,
        ]);
    }
}
