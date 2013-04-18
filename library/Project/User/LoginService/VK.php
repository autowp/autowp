<?php

class Project_User_LoginService_VK extends Project_User_LoginService_OAuth
{
    public function _processCallback($accessToken, $data)
    {
        $uaData = array(
            'external_id' => null,
            'name'        => null,
            'link'        => null,
            'photo'       => null
        );

        if (!isset($data['user_id'])) {
            throw new Exception("'user_id' was not provided");
        }

        $vkUserId = $data['user_id'];
        $uaData['external_id'] = $vkUserId;

        $json = $this->_genericApiCall('https://api.vkontakte.ru/method/getProfiles', array(
            'access_token' => $accessToken,
            'uid'          => $vkUserId,
            'fields'       => 'uid,first_name,last_name,nickname,screen_name,photo_medium'
        ));

        if (!isset($json['response'])) {
            throw new Exception('Key "response" not found');
        }

        $vkUsers = $json['response'];
        foreach ($vkUsers as $vkUser) {
            if ($vkUser['uid'] == $vkUserId) {
                $firstName = false;
                if (isset($vkUser['first_name']) && $vkUser['first_name']) {
                    $firstName = $vkUser['first_name'];
                }
                $lastName = false;
                if (isset($vkUser['last_name']) && $vkUser['last_name']) {
                    $lastName = $vkUser['last_name'];
                }
                $uaData['name'] = $firstName . ($firstName && $lastName ? ' ' : '') . $lastName;
                if (isset($vkUser['screen_name']) && $vkUser['screen_name']) {
                    $uaData['link'] = 'http://vk.com/' . $vkUser['screen_name'];
                }
                if (isset($vkUser['photo_medium']) && $vkUser['photo_medium']) {
                    $uaData['photo'] = $vkUser['photo_medium'];
                }
                break;
            }
        }

        return $uaData;
    }
}