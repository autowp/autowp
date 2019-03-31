<?php

namespace Autowp\User\Auth;

use Zend\Authentication\AuthenticationService;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Http\PhpEnvironment\Request;
use Zend\Mvc\MvcEvent;

use Autowp\User\Model\User;

class RememberDispatchListener extends AbstractListenerAggregate
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
     * @param MvcEvent $e
     */
    public function onDispatch(MvcEvent $e): void
    {
        $request = $e->getRequest();

        if ($request instanceof Request) {
            $auth = new AuthenticationService();
            if (! $auth->hasIdentity()) {
                $cookies = $request->getCookie();
                if ($cookies && isset($cookies['remember'])) {
                    $serviceManager = $e->getApplication()->getServiceManager();
                    $userModel = $serviceManager->get(User::class);
                    $adapter = new Adapter\Remember($userModel);
                    $adapter->setCredential($cookies['remember']);
                    $auth->authenticate($adapter);
                }
            }
        }
    }
}
