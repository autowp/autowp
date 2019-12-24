<?php

namespace Application;

use Locale;
use Zend\Authentication\AuthenticationService;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Http\PhpEnvironment\Request;
use Zend\Mvc\MvcEvent;
use Autowp\User\Model\User;
use Zend\Stdlib\ResponseInterface;

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
    private $skipHostname = [];

    /**
     * @var string
     */
    private $defaultLanguage = 'en';

    public function __construct(array $skipHostname)
    {
        $this->skipHostname = $skipHostname;
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
            $serviceManager = $e->getApplication()->getServiceManager();

            $hosts = $serviceManager->get('Config')['hosts'];

            $language = $this->defaultLanguage;

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $hostname = $request->getUri()->getHost();

            if (in_array($hostname, $this->skipHostname)) {
                return;
            }

            if (in_array($hostname, $this->userDetectable)) {
                $languageWhitelist = array_keys($hosts);

                $userLanguage = $this->detectUserLanguage($serviceManager, $request, $languageWhitelist);

                if (isset($hosts[$userLanguage])) {
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $hosts[$userLanguage]['hostname'] . $request->getRequestUri();

                    $this->redirect($e, $redirectUrl);
                    return;
                }
            }

            foreach ($hosts as $host) {
                if (in_array($hostname, $host['aliases'])) {
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $redirectUrl = $request->getUri()->getScheme() . '://' .
                        $host['hostname'] . $request->getRequestUri();

                    $this->redirect($e, $redirectUrl);
                    return;
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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
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
