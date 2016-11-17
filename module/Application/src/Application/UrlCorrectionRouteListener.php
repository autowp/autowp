<?php

namespace Application;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

class UrlCorrectionRouteListener extends AbstractListenerAggregate
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
            $uri = $request->getRequestUri();
        
            $method = $request->getMethod();
        
            if ($method == \Zend\Http\Request::METHOD_GET) {
        
                $filteredUri = preg_replace('|^/index\.php|isu', '', $uri);
        
                if ($filteredUri != $uri) {
                    $requestUri = $request->getUri();
                    $redirectUrl = $requestUri->getScheme() . '://' .
                        $requestUri->getHost() . $filteredUri;
        
                    return $this->redirect($e, $redirectUrl);
                }
            }
        
            $pattern = '/pictures/';
            if (strncmp($uri, $pattern, strlen($pattern)) == 0) {
                $host = 'i.wheelsage.org';
                if ($request->getUri()->getHost() != $host) {
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $host . $uri;
        
                    return $this->redirect($e, $redirectUrl);
                }
            }
        }
    }
    
    private function redirect(MvcEvent $e, $url)
    {
        $response = $e->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        $response->setStatusCode(302);
        
        return $response;
    }
}
