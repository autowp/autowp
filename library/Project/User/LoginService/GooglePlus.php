<?php

class Project_User_LoginService_GooglePlus extends Project_User_LoginService_OAuth
{
    public function _processCallback($accessToken, $data)
    {
        $uaData = array(
            'external_id' => null,
            'name'        => null,
            'link'        => null,
            'photo'       => null
        );

        $json = $this->_genericApiCall('https://www.googleapis.com/plus/v1/people/me', array(
            'access_token' => $accessToken,
            'fields'       => 'id,displayName,url,image(url)'
        ));

        if (isset($json['id']) && $json['id']) {
            $uaData['external_id'] = $json['id'];
        }
        if (isset($json['displayName']) && $json['displayName']) {
            $uaData['name'] = $json['displayName'];
        }
        if (isset($json['url']) && $json['url']) {
            $uaData['link'] = $json['url'];
        }
        if (isset($json['image']['url']) && $json['image']['url']) {
            $uaData['photo'] = $json['image']['url'];
        }

        return $uaData;
    }
}