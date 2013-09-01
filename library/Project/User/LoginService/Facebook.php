<?php

class Project_User_LoginService_Facebook extends Project_User_LoginService_Abstract
{
    protected function _getFacebook()
    {
        return new Project_Service_Facebook($this->_options);
    }

    /**
     * @param array $options
     * @return string
     */
    public function getLoginUrl(array $options)
    {
        return $this->_getFacebook()->getLoginUrl(array(
            'redirect_uri' => $options['redirect_uri']
        ));
    }

    /**
     * @param array $params
     */
    public function callback(array $params)
    {
        $facebook = $this->_getFacebook();

        $redirectUri = $params['redirect_uri'];
        unset($params['redirect_uri']);

        $token = $facebook->getAccessToken($params, $redirectUri);

        $json = $facebook->api('/me');

        $uaData = array(
            'external_id' => null,
            'name'        => null,
            'link'        => null,
            'photo'       => null
        );
        if (isset($json['id']) && $json['id']) {
            $uaData['external_id'] = $json['id'];
            $uaData['photo'] = 'https://graph.facebook.com/'.$json['id'].'/picture?type=large';
        }
        if (isset($json['name']) && $json['name']) {
            $uaData['name'] = $json['name'];
        }
        if (isset($json['link']) && $json['link']) {
            $uaData['link'] = $json['link'];
        }

        return $uaData;
    }
}