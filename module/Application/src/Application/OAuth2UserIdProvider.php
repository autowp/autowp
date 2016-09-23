<?php

namespace Application;

use Zend\Stdlib\RequestInterface;

use Zend_Auth;

class OAuth2UserIdProvider implements UserIdProviderInterface
{
    /**
     * @var Zend_Auth
     */
    private $authenticationService;

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
    public function __construct(Zend_Auth $service = null, $config = [])
    {
        $this->authenticationService = $service;

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
        if (empty($this->authenticationService)) {
            return null;
        }

        return $this->authenticationService->getIdentity();
    }
}
