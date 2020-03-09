<?php

namespace Application;

use Exception;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request;
use Laminas\Mvc\MvcEvent;
use Laminas\Session\ManagerInterface;

use function in_array;
use function session_get_cookie_params;
use function session_name;
use function session_unset;
use function setcookie;
use function time;

use const PHP_SAPI;

class SessionDispatchListener extends AbstractListenerAggregate
{
    /**
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_BOOTSTRAP, [$this, 'onBootstrap'], $priority);
    }

    public function onBootstrap(MvcEvent $e): void
    {
        if (PHP_SAPI === "cli") {
            return;
        }

        $request = $e->getRequest();
        if ($request instanceof Request) {
            $serviceManager = $e->getApplication()->getServiceManager();
            $config         = $serviceManager->get('Config');
            $sessionManager = $serviceManager->get(ManagerInterface::class);
            $sessionConfig  = $sessionManager->getConfig();

            $cookieDomain = $this->getHostCookieDomain($request, $config['hosts']);
            if ($cookieDomain) {
                $sessionConfig->setCookieDomain($cookieDomain);
            }

            try {
                $sessionManager->start();
            } catch (Exception $e) {
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

    private function getHostCookieDomain(Request $request, $hosts): ?string
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $hostname = $request->getUri()->getHost();

        foreach ($hosts as $host) {
            if ($host['hostname'] === $hostname) {
                return $host['cookie'];
            }

            if (in_array($hostname, $host['aliases'])) {
                return $host['cookie'];
            }
        }

        return null;
    }
}
