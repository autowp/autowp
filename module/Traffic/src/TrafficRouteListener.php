<?php

declare(strict_types=1);

namespace Autowp\Traffic;

use Autowp\User\Model\User;
use Autowp\User\Service\OAuth;
use Exception;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\MvcEvent;
use Laminas\Permissions\Acl\Acl;

use function strlen;
use function strncasecmp;

class TrafficRouteListener extends AbstractListenerAggregate
{
    private array $whitelist = [
        '/api/account',
        '/api/acl',
        '/api/article',
        '/api/attr',
        '/api/brands',
        '/api/chart',
        '/api/comment',
        '/api/contacts',
        '/api/donate',
        '/api/feedback',
        '/api/forum',
        '/api/hotlinks',
        '/api/ip',
        '/api/index',
        '/api/item-link',
        '/api/language',
        '/api/log',
        '/api/login',
        '/api/map',
        '/api/message',
        '/api/mosts',
        '/api/page',
        '/api/picture-moder-vote',
        '/api/pulse',
        '/api/rating',
        '/api/recaptcha',
        '/api/restore-password',
        '/api/signin',
        '/api/spec',
        '/api/text',
        '/api/timezone',
        '/api/traffic',
        '/api/stat',
        '/api/vehicle-types',
        '/api/perspective',
        '/api/perspective-page',
        '/api/picture-moder-vote-template',
        '/api/user',
        '/api/voting',
        '/comments',
        '/donate',
        '/factory',
        '/login',
        '/telegram',
    ];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -625);
    }

    private function matchWhitelist(string $url): bool
    {
        foreach ($this->whitelist as $prefix) {
            if (strncasecmp($prefix, $url, strlen($prefix)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return mixed|null
     * @throws Exception
     */
    public function onRoute(MvcEvent $e)
    {
        $request = $e->getRequest();

        if ($request instanceof Request) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            if ($this->matchWhitelist($request->getUri()->getPath())) {
                return null;
            }

            $serviceManager = $e->getApplication()->getServiceManager();

            /** @var OAuth $oauth */
            $oauth = $serviceManager->get(OAuth::class);

            $userId = $oauth->getUserID();

            $unlimitedTraffic = false;
            if ($userId) {
                $userModel = $serviceManager->get(User::class);
                $user      = $userModel->getRow(['id' => $userId]);

                if ($user) {
                    $acl              = $serviceManager->get(Acl::class);
                    $unlimitedTraffic = $acl->isAllowed($user['role'], 'website', 'unlimited-traffic');
                }
            }

            /** @var string $ip */
            $ip = $request->getServer('REMOTE_ADDR');

            if ($ip) {
                /** @var TrafficControl $service */
                $service = $serviceManager->get(TrafficControl::class);

                $banInfo = $service->getBanInfo($ip);
                if ($banInfo) {
                    $response = $e->getResponse();
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $response->setStatusCode(429);
                    $response->setContent('Access denied: ' . $banInfo['reason']);

                    return $response;
                }

                if (! $unlimitedTraffic) {
                    $service->pushHit($ip);
                }
            }
        }

        return null;
    }
}
