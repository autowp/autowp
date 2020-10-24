<?php

namespace Application;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\ResponseInterface;
use Locale;

use function array_keys;
use function in_array;

class LanguageRouteListener extends AbstractListenerAggregate
{
    private array $userDetectable = [
        'wheelsage.org',
    ];

    private string $defaultLanguage = 'en';

    public function __construct()
    {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -625);
    }

    public function onRoute(MvcEvent $e): void
    {
        $request = $e->getRequest();

        if ($request instanceof Request) {
            $serviceManager = $e->getApplication()->getServiceManager();

            $hosts = $serviceManager->get('Config')['hosts'];

            $language = $this->defaultLanguage;

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $hostname = $request->getUri()->getHost();

            if (in_array($hostname, $this->userDetectable)) {
                $languageWhitelist = array_keys($hosts);

                $userLanguage = $this->detectUserLanguage($request, $languageWhitelist);

                if (isset($hosts[$userLanguage])) {
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $redirectUrl = $request->getUri()->getScheme() . '://'
                        . $hosts[$userLanguage]['hostname'] . $request->getRequestUri();

                    $this->redirect($e, $redirectUrl);
                    return;
                }
            }

            foreach ($hosts as $host) {
                if (in_array($hostname, $host['aliases'])) {
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $redirectUrl = $request->getUri()->getScheme() . '://'
                        . $host['hostname'] . $request->getRequestUri();

                    $this->redirect($e, $redirectUrl);
                    return;
                }
            }

            foreach ($hosts as $hostLanguage => $host) {
                if ($host['hostname'] === $hostname) {
                    $language = $hostLanguage;
                    break;
                }
            }

            $translator = $serviceManager->get('MvcTranslator');
            $translator->setLocale($language);
        }
    }

    private function detectUserLanguage(
        Request $request,
        array $whitelist
    ): ?string {
        /** @var Request $acceptLanguage */
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
     */
    private function redirect(MvcEvent $e, string $url): ResponseInterface
    {
        /** @var Response $response */
        $response = $e->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $response->setStatusCode(302);

        return $response;
    }
}
