<?php

namespace Autowp\ExternalLoginService;

use Autowp\ExternalLoginService\Exception;
use Autowp\ExternalLoginService\LeagueOAuth2;
use Autowp\ExternalLoginService\Result;

use League\OAuth2\Client\Provider\Google as GoogleProvider;

class GooglePlus extends LeagueOAuth2
{
    protected function _createProvider()
    {
        return new GoogleProvider([
            'clientId'     => $this->_options['clientId'],
            'clientSecret' => $this->_options['clientSecret'],
            'redirectUri'  => $this->_options['redirect_uri'],
            'userFields'   => ['id', 'displayName', 'url', 'image(url)']
            //'hostedDomain' => 'example.com',
        ]);
    }

    protected function _getAuthorizationUrl()
    {
        return $this->_getProvider()->getAuthorizationUrl(array(
            'scope' => 'https://www.googleapis.com/auth/plus.me'
        ));
    }

    protected function _getFriendsAuthorizationUrl()
    {
        throw new Exception("Not implemented");
    }

    /**
     * @return Result
     */
    public function getData(array $options)
    {
        $provider = $this->_getProvider();

        $data = array(
            'externalId' => null,
            'name'       => null,
            'profileUrl' => null,
            'photoUrl'   => null
        );

        $ownerDetails = $provider->getResourceOwner($this->_accessToken);

        $ownerDetailsArray = $ownerDetails->toArray();

        $data['externalId'] = $ownerDetailsArray['id'];

        return new Result(array(
            'externalId' => $ownerDetailsArray['id'],
            'name'       => $ownerDetailsArray['displayName'],
            'profileUrl' => $ownerDetailsArray['url'],
            'photoUrl'   => $ownerDetailsArray['image']['url']
        ));
    }

    public function getFriendsUrl()
    {
        throw new Exception("Not implemented");
    }

    public function getFriends()
    {
        throw new Exception("Not implemented");
    }
}