<?php

namespace Application\Auth;

use Zend\Authentication\AuthenticationService;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

use Application\Auth\Adapter\Remember as RememberAuthAdapter;

class RememberDispatchListener extends AbstractListenerAggregate
{
    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 100);
    }

    /**
     * @param  MvcEvent $e
     * @return null
     */
    public function onDispatch(MvcEvent $e)
    {
        $request = $e->getRequest();

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {
            $auth = new AuthenticationService();
            if (! $auth->hasIdentity()) {
                $cookies = $request->getCookie();
                if ($cookies && isset($cookies['remember'])) {
                    $adapter = new RememberAuthAdapter();
                    $adapter->setCredential($cookies['remember']);
                    $result = $auth->authenticate($adapter);
                }
            }
        }
    }
}
