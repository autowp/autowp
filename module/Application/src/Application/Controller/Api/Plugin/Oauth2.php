<?php

namespace Application\Controller\Api\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use OAuth2\Server as OAuth2Server;
use ZF\OAuth2\Provider\UserId\UserIdProviderInterface;
use OAuth2\Request as OAuth2Request;

class Oauth2 extends AbstractPlugin
{
    /**
     * @var OAuth2Server
     */
    protected $server;

    /**
     * @var callable Factory for generating an OAuth2Server instance.
     */
    protected $serverFactory;

    /**
     * @var UserIdProviderInterface
     */
    protected $userIdProvider;

    /**
     * Constructor
     *
     * @param OAuth2Server $server
     * @param UserIdProviderInterface $userIdProvider
     */
    public function __construct($serverFactory, UserIdProviderInterface $userIdProvider)
    {
        if (! is_callable($serverFactory)) {
            throw new InvalidArgumentException(sprintf(
                'OAuth2 Server factory must be a PHP callable; received %s',
                (is_object($serverFactory) ? get_class($serverFactory) : gettype($serverFactory))
            ));
        }
        $this->serverFactory  = $serverFactory;
        $this->userIdProvider = $userIdProvider;
    }

    public function __invoke()
    {
        $token = $this->getOAuth2Server()->getAccessTokenData(OAuth2Request::createFromGlobals());
        return $token ? $token['user_id'] : null;
    }

    /**
     * Retrieve the OAuth2\Server instance.
     *
     * If not already created by the composed $serverFactory, that callable
     * is invoked with the provided $type as an argument, and the value
     * returned.
     *
     * @param string $type
     * @return OAuth2Server
     * @throws RuntimeException if the factory does not return an OAuth2Server instance.
     */
    private function getOAuth2Server()
    {
        if ($this->server instanceof OAuth2Server) {
            return $this->server;
        }

        $server = call_user_func($this->serverFactory);
        if (! $server instanceof OAuth2Server) {
            throw new RuntimeException(sprintf(
                'OAuth2\Server factory did not return a valid instance; received %s',
                (is_object($server) ? get_class($server) : gettype($server))
            ));
        }
        $this->server = $server;
        return $server;
    }
}
