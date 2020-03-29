<?php

namespace Application\Model;

use Application\Service\RabbitMQ;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use Laminas\Json\Json;

use function explode;
use function in_array;
use function parse_url;
use function trim;
use function urlencode;

use const PHP_URL_HOST;

class Referer
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

    public function addUrl(string $url, string $accept): void
    {
        $this->rabbitmq->send('hotlink', Json::encode([
            'url'       => $url,
            'accept'    => $accept,
            'timestamp' => (new DateTime())->format(DateTime::RFC3339),
        ]));
    }

    public function isImageRequest(string $accept): bool
    {
        $result = false;

        $accept = trim($accept);
        if ($accept) {
            $medias = explode(',', $accept);
            if ($medias) {
                $firstMedia = trim($medias[0]);
                if (in_array($firstMedia, ['image/png'])) {
                    $result = true;
                }
            }
        }

        return $result;
    }

    public function isHostWhitelisted(string $host): bool
    {
        $response = $this->getClient()->request('GET', '/hotlink/whitelist/' . urlencode($host), [
            'http_errors' => false,
        ]);

        $code = $response->getStatusCode();

        if ($code === 404) {
            return false;
        }

        if ($code !== 200) {
            throw new Exception("Unexpected response code `$code`");
        }

        return (bool) Json::decode($response->getBody(), Json::TYPE_ARRAY);
    }

    public function isHostBlacklisted(string $host): bool
    {
        $response = $this->getClient()->request('GET', '/hotlink/blacklist/' . urlencode($host), [
            'http_errors' => false,
        ]);

        $code = $response->getStatusCode();

        if ($code === 404) {
            return false;
        }

        if ($code !== 200) {
            throw new Exception("Unexpected response code `$code`");
        }

        return (bool) Json::decode($response->getBody(), Json::TYPE_ARRAY);
    }

    public function isUrlBlacklisted(string $url): bool
    {
        $host = @parse_url($url, PHP_URL_HOST);
        if ($host) {
            return $this->isHostBlacklisted($host);
        }

        return false;
    }

    public function addToWhitelist(string $host): void
    {
        $response = $this->getClient()->request('POST', '/hotlink/whitelist', [
            'http_errors' => false,
            'json'        => [
                'host' => $host,
            ],
        ]);

        $code = $response->getStatusCode();
        if ($code !== 201) {
            throw new Exception("Unexpected status code `$code`");
        }
    }

    public function addToBlacklist(string $host): void
    {
        $response = $this->getClient()->request('POST', '/hotlink/blacklist', [
            'http_errors' => false,
            'json'        => [
                'host' => $host,
            ],
        ]);

        $code = $response->getStatusCode();
        if ($code !== 201) {
            throw new Exception("Unexpected status code `$code`");
        }
    }

    public function flushHost(string $host): void
    {
        $response = $this->getClient()->request('DELETE', '/hotlink/monitoring', [
            'http_errors' => false,
            'query'       => ['host' => $host],
        ]);

        $code = $response->getStatusCode();
        if ($code !== 204) {
            throw new Exception("Unexpected status code `$code`");
        }
    }

    public function flush(): void
    {
        $response = $this->getClient()->request('DELETE', '/hotlink/monitoring', [
            'http_errors' => false,
        ]);

        $code = $response->getStatusCode();
        if ($code !== 204) {
            throw new Exception("Unexpected status code `$code`");
        }
    }

    public function getData(): ?array
    {
        $response = $this->getClient()->request('GET', '/hotlink/monitoring', [
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
}
