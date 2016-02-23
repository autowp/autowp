<?php

namespace Autowp\ExternalLoginService;

use Autowp\ExternalLoginService\Exception;
use Autowp\ExternalLoginService\LeagueOAuth2;
use Autowp\ExternalLoginService\Result;

use League\OAuth2\Client\Provider\Facebook as FacebookProvider;

use Zend_Date;

class Facebook extends LeagueOAuth2
{
    private $_graphApiVersion = 'v2.5';

    protected function _createProvider()
    {
        return new FacebookProvider([
            'clientId'        => $this->_options['clientId'],
            'clientSecret'    => $this->_options['clientSecret'],
            'redirectUri'     => $this->_options['redirect_uri'],
            'graphApiVersion' => $this->_graphApiVersion,
        ]);
    }

    protected function _getAuthorizationUrl()
    {
        return $this->_getProvider()->getAuthorizationUrl();
    }

    protected function _getFriendsAuthorizationUrl()
    {
        return $this->_getProvider()->getAuthorizationUrl([
            'scope' => ['public_profile', 'user_friends']
        ]);
    }

    /**
     * @var string
     */
    protected $_imageUrlTemplate =
        'https://graph.facebook.com/%s/picture?type=large';

    /**
     * @param array $options
     * @return string
     */
    /*public function getFriendsUrl(array $options)
    {
        $this->_getFacebook()->setPermission(Autowp_Service_Facebook::PERMISSION_FRIENDS);
        return $this->_getFacebook()->getLoginUrl(array(
            'redirect_uri' => $options['redirect_uri']
        ));
    }*/

    /**
     * @see Autowp_ExternalLoginService_Abstract::getData()
     * @return Autowp_ExternalLoginService_Result
     */
    public function getData(array $options)
    {
        $provider = $this->_getProvider();

        $ownerDetails = $provider->getResourceOwner($this->_accessToken);

        $json = $ownerDetails->toArray();

        $data = array(
            'externalId' => null,
            'name'       => null,
            'profileUrl' => null,
            'photoUrl'   => null,
            'birthday'   => null,
            'email'      => null,
            'residence'  => null,
            'gender'     => null
        );
        if (isset($json['id']) && $json['id']) {
            $data['externalId'] = $json['id'];
            $data['photoUrl'] = sprintf($this->_imageUrlTemplate, $json['id']);
        }
        if (isset($json['name']) && $json['name']) {
            $data['name'] = $json['name'];
        }
        if (isset($json['link']) && $json['link']) {
            $data['profileUrl'] = $json['link'];
        }
        if (isset($json['birthday']) && $json['birthday']) {
            $data['birthday'] = new Zend_Date($json['birthday'], 'MM/dd/yyyy');
        }
        if (isset($json['email']) && $json['email']) {
            $data['email'] = $json['email'];
        }
        if (isset($json['location']) && isset($json['location']['name']) && $json['location']['name']) {
            $data['residence'] = $json['location']['name'];
        }
        if (isset($json['gender']) && $json['gender']) {
            $data['gender'] = $json['gender'];
        }
        return new Result($data);
    }

    public function getFriends()
    {
        $provider = $this->_getProvider();

        if (!$this->_accessToken) {
            throw new Exception("Access token not provided");
        }

        $limit = 1000;
        $url = 'https://graph.facebook.com/' . $this->_graphApiVersion . '/me/friends?' . http_build_query([
            'limit'        => $limit,
            'offset'       => 0,
            'access_token' => $this->_accessToken->getToken()
        ]);

        $friendsId = array();
        while (true) {

            $response = file_get_contents($url);
            try {
                $response = Zend_Json::decode($response);
            } catch (Exception $e) {
                $response = null;
            }

            if ($response) {
                foreach ($response['data'] as $key => $value) {
                    $friendsId[] = (string)$value['id'];
                }
                if (count($friendsId) == 0) break;
                if (count($friendsId) == $limit && isset($response['paging']['next'])) {
                    $url = $response['paging']['next'];
                } else {
                    break;
                }
            } else {
                $message = 'Error requesting data';
                throw new Exception($message);
            }
        }
        return $friendsId;
    }
}