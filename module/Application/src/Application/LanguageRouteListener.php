<?php

namespace Application;

use Zend\Authentication\AuthenticationService;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

use Locale;

class LanguageRouteListener extends AbstractListenerAggregate
{
    /**
     * @var array
     */
    private $whitelist = [
        'fr.wheelsage.org' => 'fr',
        'en.wheelsage.org' => 'en',
        'zh.wheelsage.org' => 'zh',
        'autowp.ru'        => 'ru',
        'www.autowp.ru'    => 'ru',
        'ru.autowp.ru'     => 'ru'
    ];
    
    /**
     * @var array
     */
    private $redirects = [
        'www.wheelsage.org' => 'en.wheelsage.org',
        'wheelsage.org'     => 'en.wheelsage.org',
        'en.autowp.ru'      => 'en.wheelsage.org',
        'ru.autowp.ru'      => 'www.autowp.ru'
    ];
    
    /**
     * @var array
     */
    private $userDetectable = [
        'wheelsage.org'
    ];
    
    /**
     * @var array
     */
    private $skipHostname = ['i.wheelsage.org'];
    
    /**
     * @var string
     */
    private $defaultLanguage = 'en';
    
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
            
            $serviceManager = $e->getApplication()->getServiceManager();
        
            $language = $this->defaultLanguage;
        
            $hostname = $request->getUri()->getHost();
        
            if (in_array($hostname, $this->skipHostname)) {
                return;
            }
        
            if (in_array($hostname, $this->userDetectable)) {
        
                $languageWhitelist = array_keys($serviceManager->get('Config')['hosts']);
        
                $userLanguage = $this->detectUserLanguage($request, $languageWhitelist);
        
                $hosts = $serviceManager->get('Config')['hosts'];
        
                if (isset($hosts[$userLanguage])) {
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $hosts[$userLanguage]['hostname'] . $request->getRequestUri();
        
                    return $this->redirect($e, $redirectUrl);
                }
            }
        
            if (isset($this->redirects[$hostname])) {
        
                $redirectUrl = $request->getUri()->getScheme() . '://' .
                    $this->redirects[$hostname] . $request->getRequestUri();
        
                return $this->redirect($e, $redirectUrl);
            }
        
            if (isset($this->whitelist[$hostname])) {
                $language = $this->whitelist[$hostname];
            }
        
            $translator = $serviceManager->get('MvcTranslator');
            $translator->setLocale($language);
        }
    }
    
    private function detectUserLanguage($request, $whitelist)
    {
        $result = null;
    
        $auth = new AuthenticationService();
    
        if ($auth->hasIdentity()) {
    
            $userTable = new Model\DbTable\User();
    
            $user = $userTable->find($auth->getIdentity())->current();
    
            if ($user) {
                $isAllowed = in_array($user->language, $whitelist);
                if ($isAllowed) {
                    $result = $user->language;
                }
            }
        }
    
        if (!$result) {
            $acceptLanguage = $request->getServer('HTTP_ACCEPT_LANGUAGE');
            if ($acceptLanguage) {
                $locale = Locale::acceptFromHttp($acceptLanguage);
                if ($locale) {
                    $localeLanguage = Locale::getPrimaryLanguage($locale);
                    $isAllowed = in_array($localeLanguage, $whitelist);
                    if ($isAllowed) {
                        $result = $localeLanguage;
                    }
                }
            }
        }
    
        return $result;
    }
    
    private function redirect(MvcEvent $e, $url)
    {
        $response = $e->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        $response->setStatusCode(302);
        
        return $response;
    }
}
