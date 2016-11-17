<?php

namespace Application;

use Zend\Authentication\AuthenticationService;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

class TrafficRouteListener extends AbstractListenerAggregate
{
    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -625);
    }

    /**
     * @param  MvcEvent $e
     * @return null
     */
    public function onRoute(MvcEvent $e)
    {
        $request = $e->getRequest();

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {
            $auth = new AuthenticationService();

            $unlimitedTraffic = false;
            if ($auth->hasIdentity()) {
                $userId = $auth->getIdentity();
                $userTable = new Model\DbTable\User();
                $user = $userTable->find($userId)->current();

                if ($user) {
                    $serviceManager = $e->getApplication()->getServiceManager();
                    $acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
                    $unlimitedTraffic = $acl->isAllowed($user->role, 'website', 'unlimited-traffic');
                }
            }

            $ip = $request->getServer('REMOTE_ADDR');

            $service = new Service\TrafficControl();

            $banInfo = $service->getBanInfo($ip);
            if ($banInfo) {
                $response = $e->getResponse();
                $response->setStatusCode(403);
                $response->setContent('Access denied: ' . $banInfo['reason']);

                return $response;
            }

            if (! $unlimitedTraffic && ! $service->inWhiteList($ip)) {
                $service->pushHit($ip);
            }
        }
    }
}
