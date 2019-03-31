<?php

namespace Application;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Http\PhpEnvironment\Request;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ResponseInterface;

class HostnameCheckRouteListener extends AbstractListenerAggregate
{
    /**
     * @var string
     */
    private $defaultHostname = 'www.autowp.ru';

    /**
     * @var array
     */
    private $hostnameWhitelist = ['localhost'];

    /**
     * @var bool
     */
    private $forceHttps;

    public function __construct(array $whitelist, bool $forceHttps)
    {
        $this->hostnameWhitelist = $whitelist;
        $this->forceHttps = $forceHttps;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -625);
    }

    /**
     * @param MvcEvent $e
     * @return void
     */
    public function onRoute(MvcEvent $e): void
    {
        $request = $e->getRequest();

        if ($request instanceof Request) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $hostname = $request->getUri()->getHost();

            $isAllowed = in_array($hostname, $this->hostnameWhitelist);

            if (! $isAllowed) {
                $scheme = $this->forceHttps ? 'https' : $request->getUri()->getScheme();
                $redirectUrl = $scheme . '://' .
                    $this->defaultHostname . $request->getRequestUri();

                $this->redirect($e, $redirectUrl);
                return;
            }

            if ($this->forceHttps && $request->getUri()->getScheme() != 'https') {
                $redirectUrl = 'https://' .
                    $request->getUri()->getHost() . $request->getRequestUri();

                $this->redirect($e, $redirectUrl);
                return;
            }
        }
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @param MvcEvent $e
     * @param $url
     * @return ResponseInterface
     */
    private function redirect(MvcEvent $e, $url)
    {
        $response = $e->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $response->setStatusCode(302);

        return $response;
    }
}
