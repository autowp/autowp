<?php

use Application\Service\TrafficControl;

class BanController extends Zend_Controller_Action
{
    public function unbanIpAction()
    {
        $canBan = $this->_helper->user()->isAllowed('user', 'ban');

        $ip = $this->_getParam('ip');

        if (!$canBan || $ip === null) {
            return $this->_forward('notfound', 'error');
        }

        $service = new TrafficControl();
        $service->unban($ip);

        return $this->_redirect($this->getRequest()->getServer('HTTP_REFERER'));
    }

    public function banIpAction()
    {
        $canBan = $this->_helper->user()->isAllowed('user', 'ban');

        $ip = $this->_getParam('ip');

        if (!$canBan || $ip === null) {
            return $this->_forward('notfound', 'error');
        }

        $service = new TrafficControl();

        $service->ban(
            $ip,
            $this->getParam('period') * 3600,
            $this->_helper->user()->get()->id,
            $this->getParam('reason')
        );

        return $this->_redirect($this->getRequest()->getServer('HTTP_REFERER'));
    }

    public function banUserAction()
    {
        $users = new Users();
        $user = $users->find($this->_getParam('user_id'))->current();

        if (!$user) {
            return $this->_forward('notfound', 'error');
        }

        $canBan = $this->_helper->user()->isAllowed('user', 'ban')
              && ($this->_helper->user()->get()->id != $user->id);

        if (!$canBan || $user->last_ip === null) {
            return $this->_forward('notfound', 'error');
        }

        $service = new TrafficControl();

        $service->ban(
            inet_ntop($user->last_ip),
            $this->getParam('period') * 3600,
            $this->_helper->user()->get()->id,
            $this->getParam('reason')
        );

        return $this->_redirect($this->_helper->url->url(array(
            'action'   => 'user',
            'user_id'  => $user->id,
            'identity' => $user->identity
        ), 'users', true));
    }

    public function unbanUserAction()
    {
        $users = new Users();
        $user = $users->find($this->_getParam('user_id'))->current();

        if (!$user) {
            return $this->_forward('notfound', 'error');
        }


        $canBan = $this->_helper->user()->isAllowed('user', 'ban')
              && ($this->_helper->user()->get()->id != $user->id);

        if (!$canBan || $user->last_ip === null) {
            return $this->_forward('notfound', 'error');
        }

        $service = new TrafficControl();
        $service->unban(inet_ntop($user->last_ip));

        return $this->_redirect($this->_helper->url->url(array(
            'action'   => 'user',
            'user_id'  => $user->id,
            'identity' => $user->identity
        ), 'users', true));
    }
}