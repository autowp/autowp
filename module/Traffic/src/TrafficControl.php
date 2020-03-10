<?php

namespace Autowp\Traffic;

use Application\Service\RabbitMQ;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Laminas\Json\Json;

use function trim;

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
        if (! $this->client) {
            $this->client = new Client([
                'base_uri' => $this->url,
                'timeout'  => 5.0,
            ]);
        }

        return $this->client;
    }

    public function ban(string $ip, int $seconds, int $byUserId, string $reason): void
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException("Seconds must be > 0");
        }

        $response = $this->getClient()->request('POST', '/ban', [
            'http_errors' => false,
            'json'        => [
                'ip'         => $ip,
                'duration'   => 1000000000 * $seconds,
                'by_user_id' => $byUserId,
                'reason'     => trim($reason),
            ],
        ]);

        $code = $response->getStatusCode();
        if ($code !== 201) {
            throw new Exception("Unexpected status code `$code`");
        }
    }

    public function unban(string $ip): void
    {
        $response = $this->getClient()->request('DELETE', '/ban/' . $ip, [
            'http_errors' => false,
        ]);

        $code = $response->getStatusCode();
        if ($code !== 204) {
            throw new Exception("Unexpected status code `$code`");
        }
    }

    public function getTopData(): ?array
    {
        $response = $this->getClient()->request('GET', '/top', [
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

    public function getWhitelistData(): ?array
    {
        $response = $this->getClient()->request('GET', '/whitelist', [
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

    public function deleteFromWhitelist(string $ip): void
    {
        $response = $this->getClient()->request('DELETE', '/whitelist/' . $ip, [
            'http_errors' => false,
        ]);

        $code = $response->getStatusCode();
        if ($code !== 204) {
            throw new Exception("Unexpected status code `$code`");
        }
    }

    public function addToWhitelist(string $ip, string $description): void
    {
        $response = $this->getClient()->request('POST', '/whitelist', [
            'http_errors' => false,
            'json'        => [
                'ip'          => $ip,
                'description' => trim($description),
            ],
        ]);

        $code = $response->getStatusCode();
        if ($code !== 201) {
            throw new Exception("Unexpected status code `$code`");
        }
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
