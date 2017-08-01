<?php

namespace Autowp\Traffic\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Traffic\TrafficControl;
use Autowp\User\Model\DbTable\User;

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

    public function banUserAction()
    {
        if (! $this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $users = new User();
        $user = $users->find($this->params('user_id'))->current();

        if (! $user) {
            return $this->notFoundAction();
        }

        $canBan = $this->user()->isAllowed('user', 'ban')
            && ($this->user()->get()['id'] != $user['id']);
        if (! $canBan) {
            return $this->forbiddenAction();
        }

        if ($user['last_ip'] === null) {
            return $this->notFoundAction();
        }

        $this->service->ban(
            inet_ntop($user['last_ip']),
            $this->params()->fromPost('period') * 3600,
            $this->user()->get()['id'],
            $this->params()->fromPost('reason')
        );

        return $this->redirect()->toUrl($this->url()->fromRoute('users/user', [
            'user_id' => $user['identity'] ? $user['identity'] : 'user' . $user['id'],
        ]));
    }

    public function unbanUserAction()
    {
        if (! $this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $users = new User();
        $user = $users->find($this->params('user_id'))->current();

        if (! $user) {
            return $this->notFoundAction();
        }

        $canBan = $this->user()->isAllowed('user', 'ban')
            && ($this->user()->get()['id'] != $user['id']);

        if (! $canBan) {
            return $this->forbiddenAction();
        }

        if ($user['last_ip'] === null) {
            return $this->notFoundAction();
        }

        $this->service->unban(inet_ntop($user['last_ip']));

        return $this->redirect()->toUrl($this->url()->fromRoute('users/user', [
            'user_id' => $user['identity'] ? $user['identity'] : 'user' . $user['id']
        ]));
    }
}
