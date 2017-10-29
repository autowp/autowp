<?php

namespace Autowp\Traffic\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Traffic\TrafficControl;

class BanController extends AbstractActionController
{
    /**
     * @var TrafficControl
     */
    private $service;

    public function __construct(TrafficControl $service)
    {
        $this->service = $service;
    }

    public function unbanIpAction()
    {
        if (! $this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $canBan = $this->user()->isAllowed('user', 'ban');
        if (! $canBan) {
            return $this->forbiddenAction();
        }

        $ip = $this->params('ip');

        if ($ip === null) {
            return $this->notFoundAction();
        }

        $this->service->unban($ip);

        $referer = $this->getRequest()->getServer('HTTP_REFERER');

        return $this->redirect()->toUrl($referer ? $referer : '/');
    }

    public function banIpAction()
    {
        if (! $this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

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
            $this->user()->get()['id'],
            $this->params()->fromPost('reason')
        );

        $referer = $this->getRequest()->getServer('HTTP_REFERER');

        return $this->redirect()->toUrl($referer ? $referer : '/');
    }
}
