<?php

namespace Autowp\Traffic;

use Application\Service\RabbitMQ;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use Laminas\Json\Json;

class TrafficControl
{
    private RabbitMQ $rabbitmq;

    private string $url;

    private Client $client;

    public function __construct(
        string $url,
        RabbitMQ $rabbitmq
    ) {
        $this->url      = $url;
        $this->rabbitmq = $rabbitmq;
    }

    private function getClient(): Client
    {
        if (! isset($this->client)) {
            $this->client = new Client([
                'base_uri' => $this->url,
                'timeout'  => 5.0,
            ]);
        }

        return $this->client;
    }

    /**
     * @throws Exception
     */
    public function getUserUserPreferences(int $userId, int $toUserId): ?array
    {
        $response = $this->getClient()->request(
            'GET',
            '/user-user-preferences/' . $userId . '/' . $toUserId,
        );

        return Json::decode($response->getBody(), Json::TYPE_ARRAY);
    }

    /**
     * @throws Exception
     */
    public function getBanInfo(string $ip): ?array
    {
        $response = $this->getClient()->request('GET', '/ban/' . $ip, [
            'http_errors' => false,
        ]);

        $code = $response->getStatusCode();

        if ($code === 404) {
            return null;
        }

        if ($code !== 200) {
            throw new Exception("Unexpected response code `$code`");
        }

        return Json::decode($response->getBody(), Json::TYPE_ARRAY);
    }

    public function pushHit(string $ip): void
    {
        $this->rabbitmq->send('input', Json::encode([
            'ip'        => $ip,
            'timestamp' => (new DateTime())->format(DateTime::RFC3339),
        ]));
    }
}
