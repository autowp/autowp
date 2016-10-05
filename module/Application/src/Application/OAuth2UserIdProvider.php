<?php

namespace Application;

use Zend\Stdlib\RequestInterface;

class OAuth2UserIdProvider implements UserIdProviderInterface
{
    /**
     * @var
     */
    private $authenticationService;

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
        //$this->authenticationService = $service;

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
        if (empty($this->authenticationService)) {
            return null;
        }

        return $this->authenticationService->getIdentity();
    }
}
