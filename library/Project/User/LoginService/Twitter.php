<?php

class Project_User_LoginService_Twitter extends Project_User_LoginService_Abstract
{
    /**
     * @var Zend_Oauth_Consumer
     */
    protected $_consumer = null;

    /**
     * @var Zend_Session_Namespace
     */
    protected $_session = null;

    public function getSession()
    {
        if (!$this->_session) {
            $this->_session = new Zend_Session_Namespace(__CLASS__);
        }

        return $this->_session;
    }

    public function getConsumer(array $options = array())
    {
        if (!$this->_consumer) {
            $consumerOptions = array(
                'requestScheme'   => Zend_Oauth::REQUEST_SCHEME_HEADER,
                'requestTokenUrl' => 'https://api.twitter.com/oauth/request_token',
                'accessTokenUrl'  => 'https://api.twitter.com/oauth/access_token',
                'siteUrl'         => "http://twitter.com/oauth",
                'consumerKey'     => $this->_options['consumerKey'],
                'consumerSecret'  => $this->_options['consumerSecret'],
            );
            if (isset($options['redirect_uri'])) {
                $consumerOptions['callbackUrl'] = $options['redirect_uri'];
            }
            $this->_consumer = new Zend_Oauth_Consumer($consumerOptions);
        }

        return $this->_consumer;
    }

    /**
     * @param array $options
     * @return string
     */
    public function getLoginUrl(array $options)
    {
        $consumer = $this->getConsumer(array(
            'redirect_uri' => $options['redirect_uri']
        ));
        $this->getSession()->requestToken = $consumer->getRequestToken();

        return $consumer->getRedirectUrl();
    }

    /**
     * @param array $params
     */
    public function callback(array $params)
    {

        if (isset($params['denied']) && $params['denied']) {
            return false;
        }

        $session = $this->getSession();
        $consumer = $this->getConsumer(array(
            'redirect_uri' => $params['redirect_uri']
        ));
        $token = $consumer->getAccessToken($params, $session->requestToken);

        unset($session->requestToken);

        $twitter = new Zend_Service_Twitter(array(
            'username'        => $token->getParam('screen_name'),
            'accessToken'     => $token,
            'oauthOptions'    => array(
                'consumerKey'     => $this->_options['consumerKey'],
                'consumerSecret'  => $this->_options['consumerSecret'],
            )
        ));
        $response = $twitter->account->verifyCredentials();

        if (!$response->isSuccess()) {
            return false;
        }

        $values = $response->toValue();

        $imageUrl = null;
        if ($values->profile_image_url) {
            $imageUrl = str_replace('normal', 'bigger', $values->profile_image_url);
        }

        $uaData = array(
            'external_id' => $values->id,
            'name'        => $values->name,
            'link'        => 'http://twitter.com/' . $values->screen_name,
            'photo'       => $imageUrl
        );

        return $uaData;
    }
}