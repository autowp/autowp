<?php

namespace ApplicationTest;

use GuzzleHttp\Client;
use JsonException;
use Laminas\Http\Header\Authorization;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

class Data
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws JsonException
     */
    public static function getAdminAuthHeader(): Authorization
    {
        $client = new Client();
        $url    = 'http://goautowp-serve-public:8080/api/oauth/token';
        $res    = $client->post($url, [
            'json' => [
                'grant_type' => 'password',
                'username'   => 'admin',
                'password'   => '123123',
            ],
        ]);
        $tokens = json_decode($res->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return Authorization::fromString('Authorization: Bearer ' . $tokens['access_token']);
    }
}
