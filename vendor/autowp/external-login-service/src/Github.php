<?php

namespace Autowp\ExternalLoginService;

use Autowp\ExternalLoginService\Exception;
use Autowp\ExternalLoginService\LeagueOAuth2;
use Autowp\ExternalLoginService\Result;

use League\OAuth2\Client\Provider\Github as GithubProvider;

class Github extends LeagueOAuth2
{
    protected function _createProvider()
    {
        return new GithubProvider([
            'clientId'     => $this->_options['clientId'],
            'clientSecret' => $this->_options['clientSecret'],
            'redirectUri'  => $this->_options['redirect_uri']
        ]);
    }

    protected function _getAuthorizationUrl()
    {
        return $this->_getProvider()->getAuthorizationUrl();
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

        $ownerDetails = $provider->getResourceOwner($this->_accessToken);
        $data = $ownerDetails->toArray();

        return new Result(array(
            'externalId' => $data['id'],
            'name'       => $data['name'],
            'profileUrl' => $data['html_url'],
            'photoUrl'   => $data['avatar_url']
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