<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\User;
use Application\Service\TrafficControl;

class BanController extends AbstractActionController
{
    public function unbanIpAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $canBan = $this->user()->isAllowed('user', 'ban');

        $ip = $this->params('ip');

        if (!$canBan || $ip === null) {
            return $this->notFoundAction();
        }

        $service = new TrafficControl();
        $service->unban($ip);

        return $this->redirect()->toUrl(
            $this->getRequest()->getServer('HTTP_REFERER')
        );
    }

    public function banIpAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $canBan = $this->user()->isAllowed('user', 'ban');

        $ip = $this->params('ip');

        if (!$canBan || $ip === null) {
            return $this->notFoundAction();
        }

        $service = new TrafficControl();

        $service->ban(
            $ip,
            $this->params()->fromPost('period') * 3600,
            $this->user()->get()->id,
            $this->params()->fromPost('reason')
        );

        return $this->redirect()->toUrl($this->getRequest()->getServer('HTTP_REFERER'));
    }

    public function banUserAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $users = new User();
        $user = $users->find($this->params('user_id'))->current();

        if (!$user) {
            return $this->notFoundAction();
        }

        $canBan = $this->user()->isAllowed('user', 'ban')
              && ($this->user()->get()->id != $user->id);

        if (!$canBan || $user->last_ip === null) {
            return $this->notFoundAction();
        }

        $service = new TrafficControl();

        $service->ban(
            inet_ntop($user->last_ip),
            $this->params()->fromPost('period') * 3600,
            $this->user()->get()->id,
            $this->params()->fromPost('reason')
        );

        return $this->redirect()->toUrl($this->url()->fromRoute('users/user', [
            'user_id'  => $user->identity ? $user->identity : 'user' . $user->id,
        ]));
    }

    public function unbanUserAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $users = new User();
        $user = $users->find($this->params('user_id'))->current();

        if (!$user) {
            return $this->notFoundAction();
        }

        $canBan = $this->user()->isAllowed('user', 'ban')
              && ($this->user()->get()->id != $user->id);

        if (!$canBan || $user->last_ip === null) {
            return $this->notFoundAction();
        }

        $service = new TrafficControl();
        $service->unban(inet_ntop($user->last_ip));

        return $this->redirect()->toUrl($this->url()->fromRoute('users/user', [
            'user_id'  => $user->identity ? $user->identity : 'user' . $user->id,
        ]));
    }
}