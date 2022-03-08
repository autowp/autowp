<?php

namespace ApplicationTest;

use GuzzleHttp\Client;
use JsonException;
use Laminas\Http\Header\Authorization;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function json_decode;
use function rtrim;

use const JSON_THROW_ON_ERROR;

class Data
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws JsonException
     */
    public static function getAdminAuthHeader(array $keycloak): Authorization
    {
        $client = new Client();
        $url    = rtrim($keycloak['url'], '/')
                  . '/auth/realms/' . $keycloak['realm'] . '/protocol/openid-connect/token';
        $res    = $client->post($url, [
            'form_params' => [
                'client_id'  => 'frontend',
                'grant_type' => 'password',
                'username'   => 'admin',
                'password'   => '123123',
            ],
        ]);
        $tokens = json_decode($res->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return Authorization::fromString('Authorization: Bearer ' . $tokens['access_token']);
    }
}
