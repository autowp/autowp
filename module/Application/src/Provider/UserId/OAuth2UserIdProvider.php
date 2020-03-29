<?php

namespace Application\Provider\UserId;

use Laminas\ApiTools\OAuth2\Provider\UserId\UserIdProviderInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Stdlib\RequestInterface;

class OAuth2UserIdProvider implements UserIdProviderInterface
{
    private string $userId = 'id';

    /**
     *  Set authentication service
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        if (isset($config['zf-oauth2']['user_id'])) {
            $this->userId = $config['zf-oauth2']['user_id'];
        }
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @return mixed
     */
    public function __invoke(RequestInterface $request)
    {
        $auth = new AuthenticationService();
        return $auth->getIdentity();
    }
}
