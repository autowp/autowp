<?php

namespace Application;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\ResponseInterface;

use function in_array;

class HostnameCheckRouteListener extends AbstractListenerAggregate
{
    private string $defaultHostname = 'www.autowp.ru';

    private array $hostnameWhitelist = ['localhost'];

    private bool $forceHttps;

    public function __construct(array $whitelist, bool $forceHttps)
    {
        $this->hostnameWhitelist = $whitelist;
        $this->forceHttps        = $forceHttps;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -625);
    }

    public function onRoute(MvcEvent $e): void
    {
        $request = $e->getRequest();

        if ($request instanceof Request) {
            $hostname = $request->getUri()->getHost();

            $isAllowed = in_array($hostname, $this->hostnameWhitelist);

            if (! $isAllowed) {
                $scheme      = $this->forceHttps ? 'https' : $request->getUri()->getScheme();
                $redirectUrl = $scheme . '://'
                    . $this->defaultHostname . $request->getRequestUri();

                $this->redirect($e, $redirectUrl);
                return;
            }
        }
    }

    private function redirect(MvcEvent $e, string $url): ResponseInterface
    {
        /**
         * @var Response $response
         */
        $response = $e->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        $response->setStatusCode(302);

        return $response;
    }
}
