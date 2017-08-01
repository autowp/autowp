<?php

namespace Application;

use Zend\Authentication\AuthenticationService;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

use DateInterval;
use DateTime;

use Zend_Db_Expr;

class UserLastOnlineDispatchListener extends AbstractListenerAggregate
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 100);
    }

    /**
     * Test if the content-type received is allowable.
     *
     * @param  MvcEvent $e
     * @return null
     */
    public function onDispatch(MvcEvent $e)
    {
        $request = $e->getRequest();

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {
            $auth = new AuthenticationService();
            if ($auth->hasIdentity()) {
                $userTable = new \Autowp\User\Model\DbTable\User();

                $user = $userTable->find($auth->getIdentity())->current();

                if ($user) {
                    $changes = false;
                    $nowExpiresDate = (new DateTime())->sub(new DateInterval('PT1S'));
                    $lastOnline = $user->getDateTime('last_online');
                    if (! $lastOnline || ($lastOnline < $nowExpiresDate)) {
                        $user['last_online'] = new Zend_Db_Expr('NOW()');
                        $changes = true;
                    }

                    $remoteAddr = $request->getServer('REMOTE_ADDR');
                    if ($remoteAddr) {
                        $ip = inet_pton($remoteAddr);
                        if ($ip != $user['last_ip']) {
                            $user['last_ip'] = $ip;
                            $changes = true;
                        }
                    }

                    if ($changes) {
                        $user->save();
                    }
                }
            }
        }
    }
}
