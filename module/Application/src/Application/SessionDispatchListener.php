<?php

namespace Application;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Http\Request;
use Zend\Mvc\MvcEvent;
use Zend\Session\ManagerInterface;

class SessionDispatchListener extends AbstractListenerAggregate
{
    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_BOOTSTRAP, [$this, 'onBootstrap'], $priority);
    }

    /**
     * @param  MvcEvent $e
     * @return null
     */
    public function onBootstrap(MvcEvent $e)
    {
        $request = $e->getRequest();
        if ($request instanceof Request) {
            $serviceManager = $e->getApplication()->getServiceManager();
            $sessionManager = $serviceManager->get(ManagerInterface::class);
            $config = $sessionManager->getConfig();
            
            $cookieDomain = $this->getHostCookieDomain($request);
            if ($cookieDomain) {
                $config->setCookieDomain($cookieDomain);
                //$sessionManager->start();
            }
            
            /*$cookies = $request->getCookie();
            $key = $config->getName();
            if (isset($cookies[$key])) {
                $sessionManager->start();
            }*/
        }
    }

    private function getHostCookieDomain(Request $request)
    {
        $hostname = $request->getUri()->getHost();

        switch ($hostname) {
            case 'www.autowp.ru':
            case 'autowp.ru':
                return '.autowp.ru';
            default:
                return '.wheelsage.org';
        }

        return null;
    }
}
