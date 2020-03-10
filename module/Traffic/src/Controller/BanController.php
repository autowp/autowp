<?php

namespace Autowp\Traffic\Controller;

use Autowp\Traffic\TrafficControl;
use Laminas\Mvc\Controller\AbstractActionController;

class BanController extends AbstractActionController
{
    private TrafficControl $service;

    public function __construct(TrafficControl $service)
    {
        $this->service = $service;
    }

    public function unbanIpAction()
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if (! $this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $canBan = $this->user()->isAllowed('user', 'ban');
        if (! $canBan) {
            return $this->forbiddenAction();
        }

        $ip = $this->params('ip');

        if ($ip === null) {
            return $this->notFoundAction();
        }

        $this->service->unban($ip);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $referer = $this->getRequest()->getServer('HTTP_REFERER');

        return $this->redirect()->toUrl($referer ? $referer : '/');
    }

    public function banIpAction()
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if (! $this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $canBan = $this->user()->isAllowed('user', 'ban');
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
            $this->user()->get()['id'], // @phan-suppress-current-line PhanUndeclaredMethod
            $this->params()->fromPost('reason')
        );

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $referer = $this->getRequest()->getServer('HTTP_REFERER');

        return $this->redirect()->toUrl($referer ? $referer : '/');
    }
}
