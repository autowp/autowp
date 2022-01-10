<?php

namespace ApplicationTest;

use GuzzleHttp\Client;
use JsonException;
use Laminas\Http\Header\Authorization;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function json_decode;
use function rtrim;
use function urlencode;

use const JSON_THROW_ON_ERROR;

class Data
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws JsonException
     */
    public static function getAdminAuthHeader(ServiceManager $serviceManager): Authorization
    {
        $keycloakConfig = $serviceManager->get('config')['keycloak'];

        $client = new Client();
        $url    = rtrim($keycloakConfig['url']) . '/auth/realms/'
                . urlencode($keycloakConfig['realm']) . '/protocol/openid-connect/token';
        $res    = $client->post($url, [
            'auth'        => ['autowp', 'c0fce0df-6105-4d1e-bc23-8e67239f7640'],
            'form_params' => [
                'grant_type' => 'password',
                'username'   => 'admin',
                'password'   => '123123',
            ],
        ]);
        $tokens = json_decode($res->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return Authorization::fromString('Authorization: Bearer ' . $tokens['access_token']);
    }
}
