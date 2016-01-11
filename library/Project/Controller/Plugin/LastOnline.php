<?php

/**
 * @author Dima
 * @todo refactor to controller action
 *
 */
class Project_Controller_Plugin_LastOnline extends Zend_Controller_Plugin_Abstract
{
    /**
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
        $auth = Zend_Auth::getInstance();
        if ($auth->hasIdentity()) {

            $userTable = new Users();

            $user = $userTable->find($auth->getIdentity())->current();

            if ($user) {
                $changes = false;
                $nowExpiresDate = Zend_Date::now()->subMinute(1);
                $lastOnline = $user->getDate('last_online');
                if (!$lastOnline || ($lastOnline->isEarlier($nowExpiresDate))) {
                    $user->last_online = new Zend_Db_Expr('NOW()');
                    $changes = true;
                }

                $ip = inet_pton($request->getServer('REMOTE_ADDR'));
                if ($ip != $user->last_ip) {
                    $user->last_ip = $ip;
                    $changes = true;
                }

                if ($changes) {
                    $user->save();
                }
            }
        }
    }
}