<?php

namespace Application\Provider\UserId;

use Zend\Stdlib\RequestInterface;

use Zend_Auth;

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
     * @param Zend_Auth $service
     * @param array $config
     */
    public function __construct($config = [])
    {
        if (isset($config['zf-oauth2']['user_id'])) {
            $this->userId = $config['zf-oauth2']['user_id'];
        }
    }

    /**
     * Use Zend_Auth to fetch the identity.
     *
     * @param  RequestInterface $request
     * @return mixed
     */
    public function __invoke(RequestInterface $request)
    {
        return Zend_Auth::getInstance()->getIdentity();
    }
}
