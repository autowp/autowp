<?php

namespace Autowp\Traffic;

use DateInterval;
use DateTime;
use DateTimeZone;

use GuzzleHttp\Client;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;
use Zend\Json\Json;

use Application\Service\RabbitMQ;

class TrafficControl
{
    /**
     * @var RabbitMQ
     */
    private $rabbitmq;

    /**
     * @var TableGateway
     */
    private $whitelistTable;

    /**
     * @var TableGateway
     */
    private $monitoringTable;

    /**
     * @var string
     */
    private $url;

    public function __construct(
        string $url,
        RabbitMQ $rabbitmq,
        TableGateway $whitelistTable,
        TableGateway $monitoringTable
    ) {
        $this->url = $url;
        $this->rabbitmq = $rabbitmq;
        $this->whitelistTable = $whitelistTable;
        $this->monitoringTable = $monitoringTable;
    }

    private function getClient(): Client
    {
        return new Client([
            'base_uri' => $this->url,
            'timeout'  => 10.0,
        ]);
    }

    public function ban(string $ip, int $seconds, int $byUserId, string $reason): void
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException("Seconds must be > 0");
        }

        $response = $this->getClient()->request('POST', '/ban', [
            'http_errors' => false,
            'json' => [
                'ip'         => $ip,
                'duration'   => 1000000000 * $seconds,
                'by_user_id' => $byUserId,
                'reason'     => trim($reason)
            ]
        ]);

        $code = $response->getStatusCode();
        if ($code != 201) {
            throw new \Exception("Unexpected status code `$code`");
        }
    }

    public function unban(string $ip): void
    {
        $response = $this->getClient()->request('DELETE', '/ban/' . $ip, [
            'http_errors' => false
        ]);

        $code = $response->getStatusCode();
        if ($code != 204) {
            throw new \Exception("Unexpected status code `$code`");
        }
    }

    /**
     * @param string $ip
     * @return bool
     */
    private function inWhiteListBin($ip)
    {
        return (bool)$this->whitelistTable->select([
            'ip = UNHEX(?)' => bin2hex($ip)
        ])->current();
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function inWhiteList($ip)
    {
        return (bool)$this->whitelistTable->select([
            'ip = INET6_ATON(?)' => (string)$ip
        ])->current();
    }

    /**
     * @return array
     */
    public function getTopData()
    {
        $rows = $this->monitoringTable->select(function (Select $select) {
            $select
                ->columns([
                    'ip',
                    'ip_text' => new Expression('INET6_NTOA(ip)'),
                    'count'   => new Expression('SUM(count)')
                ])
                ->where('day_date = CURDATE()')
                ->group('ip')
                ->order('count DESC')
                ->limit(50);
        });

        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'ip'        => $row['ip_text'],
                'count'     => $row['count'],
                'ban'       => $this->getBanInfo($row['ip_text']),
                'whitelist' => $this->inWhiteListBin($row['ip'])
            ];
        }
        unset($row);

        return $result;
    }

    /**
     * @return array
     */
    public function getWhitelistData()
    {
        $rows = $this->whitelistTable->select(function (Select $select) {
            $select
                ->columns([
                    'description',
                    'ip_text' => new Expression('INET6_NTOA(ip)')
                ])
                ->order('ip');
        });

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'ip'          => $row['ip_text'],
                'description' => $row['description']
            ];
        }

        return $items;
    }

    /**
     * @param string $ip
     */
    public function deleteFromWhitelist($ip)
    {
        $this->whitelistTable->delete([
            'ip = INET6_ATON(?)' => $ip
        ]);
    }

    /**
     * @param string $ip
     * @param string $description
     */
    public function addToWhitelist($ip, $description)
    {
        $this->unban($ip);

        $row = $this->whitelistTable->select([
            'ip = inet6_aton(?)' => $ip
        ])->current();

        if (! $row) {
            $this->whitelistTable->insert([
                'ip'          => new Expression('inet6_aton(?)', $ip),
                'description' => $description
            ]);
        }
    }

    /**
     * @return boolean|array
     */
    public function getBanInfo(string $ip)
    {
        $response = $this->getClient()->request('GET', '/ban/' . $ip, [
            'http_errors' => false
        ]);

        $code = $response->getStatusCode();

        if ($code == 404) {
            return false;
        }

        if ($code != 200) {
            throw new \Exception("Unexpected response code `$code`");
        }

        return Json::decode($response->getBody(), Json::TYPE_ARRAY);
    }

    public function pushHit(string $ip): void
    {
        $this->rabbitmq->send('input', Json::encode([
            'ip'        => $ip,
            'timestamp' => (new \DateTime())->format(\DateTime::RFC3339)
        ]));
    }
}
