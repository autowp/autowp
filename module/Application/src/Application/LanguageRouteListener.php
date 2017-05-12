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

            $hosts = $serviceManager->get('Config')['hosts'];

            $language = $this->defaultLanguage;

            $hostname = $request->getUri()->getHost();

            if (in_array($hostname, $this->skipHostname)) {
                return;
            }

            if (in_array($hostname, $this->userDetectable)) {
                $languageWhitelist = array_keys($hosts);

                $userLanguage = $this->detectUserLanguage($request, $languageWhitelist);

                if (isset($hosts[$userLanguage])) {
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $hosts[$userLanguage]['hostname'] . $request->getRequestUri();

                    return $this->redirect($e, $redirectUrl);
                }
            }

            foreach ($hosts as $host) {
                if (in_array($hostname, $host['aliases'])) {
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $host['hostname'] . $request->getRequestUri();

                    return $this->redirect($e, $redirectUrl);
                }
            }

            foreach ($hosts as $hostLanguage => $host) {
                if ($host['hostname'] == $hostname) {
                    $language = $hostLanguage;
                    break;
                }
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
            $userTable = new \Autowp\User\Model\DbTable\User();

            $user = $userTable->find($auth->getIdentity())->current();

            if ($user) {
                $isAllowed = in_array($user->language, $whitelist);
                if ($isAllowed) {
                    $result = $user->language;
                }
            }
        }

        if (! $result) {
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
