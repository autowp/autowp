<?php

namespace Application;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
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
     * Test if the content-type received is allowable.
     *
     * @param  MvcEvent $e
     * @return null
     */
    public function onBootstrap(MvcEvent $e)
    {
        $cookieDomain = $this->getHostCookieDomain($e->getRequest());
        if ($cookieDomain) {
            $serviceManager = $e->getApplication()->getServiceManager();
            $sessionManager = $serviceManager->get(ManagerInterface::class);
            $sessionManager->getConfig()->setStorageOption('cookie_domain', $cookieDomain);
            $sessionManager->start();
        }
    }

    private function getHostCookieDomain($request)
    {
        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {
            $hostname = $request->getUri()->getHost();

            switch ($hostname) {
                case 'www.autowp.ru':
                case 'autowp.ru':
                    return '.autowp.ru';
                default:
                    return '.wheelsage.org';
            }
        }

        return null;
    }
}
