<?php

/**
 * @see Zend_Json
 */
require_once 'Zend/Json.php';

/**
 * @see Zend_Session_Namespace
 */
require_once 'Zend/Session/Namespace.php';

class Project_Oauth2
{
    /**
     * @var Zend_Session_Namespace
     */
    protected $_session = null;

    /**
     * @var string
     */
    protected $_authUri = null;

    /**
     * @var string
     */
    protected $_tokenUri = null;

    /**
     * @var string
     */
    protected $_clientId = null;

    /**
     * @var string
     */
    protected $_clientSecret = null;

    /**
     * @var array|string
     */
    protected $_scope = null;

    /**
     * @var string
     */
    protected $_scopeDelim = null;

    /**
     * @var string
     */
    protected $_state = null;

    /**
     * @param array|Zend_Config $options
     */
    public function __construct($options = null)
    {
        if (!is_null($options)) {
            if ($options instanceof Zend_Config) {
                $options = $options->toArray();
            }
            $this->setOptions($options);
        }
    }

    /**
     * Parse option array or Zend_Config instance and setup options using their
     * relevant mutators.
     *
     * @param array|Zend_Config $options
     * @return Project_Oauth2
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            switch ($key) {
                case 'authUri':
                    $this->setAuthUri($value);
                    break;
                case 'tokenUri':
                    $this->setTokenUri($value);
                    break;
                case 'clientId':
                    $this->setClientId($value);
                    break;
                case 'clientSecret':
                    $this->setClientSecret($value);
                    break;
                case 'scope':
                    $this->setScope($value);
                    break;
                case 'scopeDelim':
                    $this->setScopeDelim($value);
                    break;
                case 'session':
                    $this->setSession($value);
                    break;
            }
        }

        return $this;
    }

    /**
     * @param string $authUri
     * @return Project_Oauth2
     */
    public function setAuthUri($authUri)
    {
        $this->_authUri = (string)$authUri;
        return $this;
    }

    /**
     * @param string $tokenUri
     * @return Project_Oauth2
     */
    public function setTokenUri($tokenUri)
    {
        $this->_tokenUri = (string)$tokenUri;
        return $this;
    }

    /**
     * @param string $clientId
     * @return Project_Oauth2
     */
    public function setClientId($clientId)
    {
        $this->_clientId = (string)$clientId;
        return $this;
    }

    /**
     * @param string $clientSecret
     * @return Project_Oauth2
     */
    public function setClientSecret($clientSecret)
    {
        $this->_clientSecret = (string)$clientSecret;
        return $this;
    }

    /**
     * @param string|array $scope
     * @return Project_Oauth2
     */
    public function setScope($scope)
    {
        $this->_scope = $scope;
        return $this;
    }

    /**
     * @param string $scopeDelim
     * @return Project_Oauth2
     */
    public function setScopeDelim($scopeDelim)
    {
        $this->_scopeDelim = (string)$scopeDelim;
        return $this;
    }

    /**
     * @param Zend_Session_Namespace $session
     * @return Project_Oauth2
     */
    public function setSession(Zend_Session_Namespace $session)
    {
        $this->_session = $session;
        return $this;
    }

    /**
     * @return Zend_Session_Namespace
     */
    public function getSession()
    {
        return $this->_session;
    }

    /**
     *
     */
    protected function establishCSRFTokenState()
    {
        if ($this->_state === null) {
            $this->_state = md5(uniqid(mt_rand(), true));
            $this->getSession()->state = $this->_state;
        }
    }

    /**
     * @param array $params
     */
    public function getLoginUrl(array $params = array())
    {
        $this->establishCSRFTokenState();

        if (!isset($params['redirect_uri'])) {
            return $this->_raise("'redirect_uri' was not provided");
        }
        $redirectUri = $params['redirect_uri'];

        $url = $this->_authUri . '?' . http_build_query(array(
            'response_type' => 'code',
            'client_id'     => $this->_clientId,
            'redirect_uri'  => $redirectUri,
            'scope'         => implode($this->_scopeDelim, (array)$this->_scope),
            'state'         => $this->_state,
            'access_type'   => 'online'
        ));

        return $url;
    }

    /**
     * @param array $params
     * @param array $result
     * @throws Project_Oauth2_Exception
     * @return string
     */
    public function getAccessToken(array $params = array(), &$result = array())
    {
        // check state
        $state = null;
        if (!isset($params['state'])) {
            return $this->_raise('State was not provided');
        }
        $state = (string)$params['state'];

        $session = $this->getSession();
        if (!isset($session->state)) {
            return $this->_raise('State was not registered');
        }

        if ($session->state != $state) {
            return $this->_raise('State wrong. CSRF attempt?');
        }
        unset($session->state);

        $code = isset($params['code']) ? $params['code'] : null;
        if (!isset($params['code'])) {
            return $this->_raise('Code was not provided');
        }
        $code = (string)$params['code'];

        if (!isset($params['redirect_uri'])) {
            return $this->_raise("'redirect_uri' was not provided");
        }

        require_once 'Zend/Http/Client.php';
        $client = new Zend_Http_Client($this->_tokenUri);
        $client->setParameterPost(array(
            'code'          => $code,
            'client_id'     => $this->_clientId,
            'client_secret' => $this->_clientSecret,
            'redirect_uri'  => $params['redirect_uri'],
            'grant_type'    => 'authorization_code'
        ));
        $response = $client->request(Zend_Http_Client::POST);
        if (!$response->isSuccessful()) {
            $body = $response->getBody();

            $httpErrorMessage = 'HTTP ' . $response->getStatus() . ' ' . $response->getMessage();
            $oauth2ErrorMessage = false;
            try {
                $json = Zend_Json::decode($body);
                $oauth2ErrorMessage = isset($json['error']) ? $json['error'] : null;
            } catch (Zend_Json_Exception $e) {

            }
            $errorMessage = $httpErrorMessage;
            if ($oauth2ErrorMessage) {
                $errorMessage .= ' (' . $oauth2ErrorMessage . ')';
            }
            return $this->_raise($errorMessage);
        }

        $body = $response->getBody();
        $json = Zend_Json::decode($body);

        if (isset($json['error']) && $json['error']) {
            $errorMessage = $json['error'];
            if (isset($json['error_description']) && $json['error_description']) {
                $errorMessage .= ' (' . $json['error_description'] . ')';
            }
            return $this->_raise($errorMessage);
        }

        if (!isset($json['access_token'])) {
            return $this->_raise("'access_token' was not provided");
        }

        $result = $json;
        return $json['access_token'];
    }

    /**
     * @param string $message
     * @throws Project_Oauth2_Exception
     */
    protected function _raise($message)
    {
        require_once 'Project/Oauth2/Exception.php';
        throw new Project_Oauth2_Exception($message);
    }
}