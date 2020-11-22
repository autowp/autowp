<?php

namespace Autowp\Traffic\Controller;

use Autowp\Traffic\TrafficControl;
use Autowp\User\Controller\Plugin\User;
use Exception;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

/**
 * @method ViewModel forbiddenAction()
 * @method User user($user = null)
 */
class BanController extends AbstractActionController
{
    private TrafficControl $service;

    public function __construct(TrafficControl $service)
    {
        $this->service = $service;
    }

    /**
     * @return Response|ViewModel
     * @throws Exception
     */
    public function unbanIpAction()
    {
        /** @var Request $request */
        $request = $this->getRequest();
        if (! $request->isPost()) {
            return $this->forbiddenAction();
        }

        $canBan = $this->user()->enforce('user', 'ban');
        if (! $canBan) {
            return $this->forbiddenAction();
        }

        $ip = $this->params('ip');

        if ($ip === null) {
            return $this->notFoundAction();
        }

        $this->service->unban($ip);

        $referer = $request->getServer('HTTP_REFERER');

        return $this->redirect()->toUrl($referer ? $referer : '/');
    }

    /**
     * @return Response|ViewModel
     * @throws Exception
     */
    public function banIpAction()
    {
        /** @var Request $request */
        $request = $this->getRequest();
        if (! $request->isPost()) {
            return $this->forbiddenAction();
        }

        $canBan = $this->user()->enforce('user', 'ban');
        if (! $canBan) {
            return $this->forbiddenAction();
        }

        $ip = $this->params('ip');

        if ($ip === null) {
            return $this->notFoundAction();
        }

        $this->service->ban(
            $ip,
            $this->params()->fromPost('period') * 3600,
            $this->user()->get()['id'],
            $this->params()->fromPost('reason')
        );

        $referer = $request->getServer('HTTP_REFERER');

        return $this->redirect()->toUrl($referer ? $referer : '/');
    }
}
