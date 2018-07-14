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
        if (php_sapi_name() == "cli") {
            return;
        }

        $request = $e->getRequest();
        if ($request instanceof Request) {
            $serviceManager = $e->getApplication()->getServiceManager();
            $config = $serviceManager->get('Config');
            $sessionManager = $serviceManager->get(ManagerInterface::class);
            $sessionConfig = $sessionManager->getConfig();

            $cookieDomain = $this->getHostCookieDomain($request, $config['hosts']);
            if ($cookieDomain) {
                $sessionConfig->setCookieDomain($cookieDomain);
            }

            try {
                $sessionManager->start();
            } catch (\Exception $e) {
                session_unset();
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            /*$cookies = $request->getCookie();
            $key = $config->getName();
            if (isset($cookies[$key])) {
                $sessionManager->start();
            }*/
        }
    }

    private function getHostCookieDomain(Request $request, $hosts)
    {
        $hostname = $request->getUri()->getHost();

        foreach ($hosts as $host) {
            if ($host['hostname'] == $hostname) {
                return $host['cookie'];
            }

            if (in_array($hostname, $host['aliases'])) {
                return $host['cookie'];
            }
        }

        return null;
    }
}
