<?php

namespace Application;

use Locale;

use Zend\Authentication\AuthenticationService;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

use Autowp\User\Model\User;

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

                $userLanguage = $this->detectUserLanguage($serviceManager, $request, $languageWhitelist);

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

    private function detectUserLanguage($serviceManager, $request, $whitelist)
    {
        $auth = new AuthenticationService();

        if ($auth->hasIdentity()) {
            $userModel = $serviceManager->get(User::class);

            $userLanguage = $userModel->getUserLanguage($auth->getIdentity());
            if (in_array($userLanguage, $whitelist)) {
                return $userLanguage;
            }
        }

        $acceptLanguage = $request->getServer('HTTP_ACCEPT_LANGUAGE');
        if ($acceptLanguage) {
            $locale = Locale::acceptFromHttp($acceptLanguage);
            if ($locale) {
                $localeLanguage = Locale::getPrimaryLanguage($locale);
                if (in_array($localeLanguage, $whitelist)) {
                    return $localeLanguage;
                }
            }
        }

        return null;
    }

    private function redirect(MvcEvent $e, $url)
    {
        $response = $e->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        $response->setStatusCode(302);

        return $response;
    }
}
