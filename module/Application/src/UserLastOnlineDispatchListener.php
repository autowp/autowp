<?php

namespace Application;

use Autowp\User\Model\User;
use Autowp\User\Service\OAuth;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\MvcEvent;

class UserLastOnlineDispatchListener extends AbstractListenerAggregate
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 100);
    }

    /**
     * Test if the content-type received is allowable.
     */
    public function onDispatch(MvcEvent $e): void
    {
        $request = $e->getRequest();

        if ($request instanceof Request) {
            $serviceManager = $e->getApplication()->getServiceManager();

            /** @var OAuth $oauth */
            $oauth  = $serviceManager->get(OAuth::class);
            $userId = $oauth->getUserID();

            if ($userId) {
                /** @var User $userModel */
                $userModel = $serviceManager->get(User::class);
                $userModel->registerVisit($userId, $request);
            }
        }
    }
}
