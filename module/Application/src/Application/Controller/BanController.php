<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Service\TrafficControl;

use Users;

class BanController extends AbstractActionController
{
    public function unbanIpAction()
    {
        $canBan = $this->user()->isAllowed('user', 'ban');

        $ip = $this->params('ip');

        if (!$canBan || $ip === null) {
            return $this->getResponse()->setStatusCode(404);
        }

        $service = new TrafficControl();
        $service->unban($ip);

        return $this->redirect()->toUrl(
            $this->getRequest()->getServer('HTTP_REFERER')
        );
    }

    public function banIpAction()
    {
        $canBan = $this->user()->isAllowed('user', 'ban');

        $ip = $this->params('ip');

        if (!$canBan || $ip === null) {
            return $this->getResponse()->setStatusCode(404);
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
        $users = new Users();
        $user = $users->find($this->params('user_id'))->current();

        if (!$user) {
            return $this->getResponse()->setStatusCode(404);
        }

        $canBan = $this->user()->isAllowed('user', 'ban')
              && ($this->user()->get()->id != $user->id);

        if (!$canBan || $user->last_ip === null) {
            return $this->getResponse()->setStatusCode(404);
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
        $users = new Users();
        $user = $users->find($this->params('user_id'))->current();

        if (!$user) {
            return $this->getResponse()->setStatusCode(404);
        }

        $canBan = $this->user()->isAllowed('user', 'ban')
              && ($this->user()->get()->id != $user->id);

        if (!$canBan || $user->last_ip === null) {
            return $this->getResponse()->setStatusCode(404);
        }

        $service = new TrafficControl();
        $service->unban(inet_ntop($user->last_ip));

        return $this->redirect()->toUrl($this->url()->fromRoute('users/user', [
            'user_id'  => $user->identity ? $user->identity : 'user' . $user->id,
        ]));
    }
}