<?php

namespace Application\Provider\UserId;

use Zend\Authentication\AuthenticationService;
use Zend\Stdlib\RequestInterface;

use ZF\OAuth2\Provider\UserId\UserIdProviderInterface;

class OAuth2UserIdProvider implements UserIdProviderInterface
{
    /**
     * @var string
     */
    private $userId = 'id';

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
     * @param  RequestInterface $request
     * @return mixed
     */
    public function __invoke(RequestInterface $request)
    {
        $auth = new AuthenticationService();
        return $auth->getIdentity();
    }
}
