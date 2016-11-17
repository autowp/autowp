<?php

namespace Application;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

class HostnameCheckRouteListener extends AbstractListenerAggregate
{
    /**
     * @var string
     */
    private $defaultHostname = 'www.autowp.ru';
    
    /**
     * @var array
     */
    private $hostnameWhitelist = [
        'www.autowp.ru', 'ru.autowp.ru', 'en.autowp.ru',
        'i.wheelsage.org', 'en.wheelsage.org', 'fr.wheelsage.org',
        'zh.wheelsage.org', 'www.wheelsage.org', 'wheelsage.org'
    ];
    
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
            $hostname = $request->getUri()->getHost();
        
            $isAllowed = in_array($hostname, $this->hostnameWhitelist);
        
            if (!$isAllowed) {
                $redirectUrl = $request->getUri()->getScheme() . '://' .
                    $this->defaultHostname . $request->getRequestUri();
        
                return $this->redirect($e, $redirectUrl);
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
