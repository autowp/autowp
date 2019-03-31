<?php

namespace Application;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Http\Request;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ResponseInterface;

class UrlCorrectionRouteListener extends AbstractListenerAggregate
{
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
     * @param  MvcEvent $e
     * @return null
     */
    public function onRoute(MvcEvent $e): void
    {
        $request = $e->getRequest();

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {
            $uri = $request->getRequestUri();

            $method = $request->getMethod();

            if ($method == Request::METHOD_GET) {
                $filteredUri = preg_replace('|^/index\.php|isu', '', $uri);

                if ($filteredUri != $uri) {
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $requestUri = $request->getUri();
                    $redirectUrl = $requestUri->getScheme() . '://' .
                        $requestUri->getHost() . $filteredUri;

                    $this->redirect($e, $redirectUrl);
                    return;
                }
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
