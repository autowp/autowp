<?php

namespace Application;

use Zend\Authentication\AuthenticationService;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Http\PhpEnvironment\Request;
use Zend\Mvc\MvcEvent;
use Autowp\User\Model\User;

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

        if ($request instanceof Request) {
            $auth = new AuthenticationService();
            if ($auth->hasIdentity()) {
                $serviceManager = $e->getApplication()->getServiceManager();
                $userModel = $serviceManager->get(User::class);
                $userModel->registerVisit($auth->getIdentity(), $request);
            }
        }
    }
}
