<?php

namespace Autowp\User\Auth;

use Autowp\User\Model\User;
use Laminas\Authentication\AuthenticationService;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\MvcEvent;

class RememberDispatchListener extends AbstractListenerAggregate
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 100);
    }

    public function onDispatch(MvcEvent $e): void
    {
        $request = $e->getRequest();

        if ($request instanceof Request) {
            $auth = new AuthenticationService();
            if (! $auth->hasIdentity()) {
                $cookies = $request->getCookie();
                if ($cookies && isset($cookies['remember'])) {
                    $serviceManager = $e->getApplication()->getServiceManager();
                    $userModel      = $serviceManager->get(User::class);
                    $adapter        = new Adapter\Remember($userModel);
                    $adapter->setCredential($cookies['remember']);
                    $auth->authenticate($adapter);
                }
            }
        }
    }
}
