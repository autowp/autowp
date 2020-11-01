<?php

namespace Application;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\MvcEvent;

use function preg_replace;

class UrlCorrectionRouteListener extends AbstractListenerAggregate
{
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
            $uri = $request->getRequestUri();

            $method = $request->getMethod();

            if ($method === Request::METHOD_GET) {
                $filteredUri = preg_replace('|^/index\.php|isu', '', $uri);

                if ($filteredUri !== $uri) {
                    $requestUri  = $request->getUri();
                    $redirectUrl = $requestUri->getScheme() . '://'
                        . $requestUri->getHost() . $filteredUri;

                    $this->redirect($e, $redirectUrl);
                    return;
                }
            }
        }
    }

    private function redirect(MvcEvent $e, string $url): Response
    {
        /** @var Response $response */
        $response = $e->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        $response->setStatusCode(302);

        return $response;
    }
}
