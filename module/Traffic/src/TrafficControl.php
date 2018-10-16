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
    private $bannedTable;

    /**
     * @var TableGateway
     */
    private $whitelistTable;

    /**
     * @var TableGateway
     */
    private $monitoringTable;

    public function __construct(
        RabbitMQ $rabbitmq,
        TableGateway $bannedTable,
        TableGateway $whitelistTable,
        TableGateway $monitoringTable
    ) {
        $this->rabbitmq = $rabbitmq;
        $this->bannedTable = $bannedTable;
        $this->whitelistTable = $whitelistTable;
        $this->monitoringTable = $monitoringTable;
    }

    private function getClient(): Client
    {
        return new Client([
            'base_uri' => 'http://traffic',
            'timeout'  => 10.0,
        ]);
    }

    public function ban(string $ip, int $seconds, int $byUserId, string $reason): void
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException("Seconds must be > 0");
        }

        $response = $this->getClient()->request('POST', '/ban', [
            'form_params' => [
                'ip'         => $ip,
                'duration'   => 1000000000 * $seconds,
                'by_user_id' => $byUserId,
                'reason'     => trim($reason)
            ]
        ]);

        if ($response->getStatusCode() != 201) {
            throw new \Exception("Failed to add ban");
        }
    }

    public function unban(string $ip): void
    {
        $response = $this->getClient()->request('DELETE', '/ban/' . $ip);

        if ($response->getStatusCode() != 204) {
            throw new \Exception("Failed to unban");
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
            $banRow = $this->bannedTable->select([
                'ip = unhex(?)' => bin2hex($row['ip']),
                'up_to >= NOW()'
            ])->current();

            $result[] = [
                'ip'        => $row['ip_text'],
                'count'     => $row['count'],
                'ban'       => $banRow,
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
     * @param string $ip
     * @return boolean|array
     */
    public function getBanInfo($ip)
    {
        $row = $this->bannedTable->select([
            'ip = INET6_ATON(?)' => (string)$ip,
            'up_to >= NOW()'
        ])->current();

        if (! $row) {
            return false;
        }

        return [
            'up_to'   => DateTime::createFromFormat(
                MYSQL_DATETIME_FORMAT,
                $row['up_to'],
                new DateTimeZone(MYSQL_TIMEZONE)
            ),
            'user_id' => $row['by_user_id'],
            'reason'  => $row['reason']
        ];
    }

    public function pushHit(string $ip): void
    {
        $this->rabbitmq->send('input', Json::encode([
            'ip'        => $ip,
            'timestamp' => (new \DateTime())->format(\DateTime::RFC3339)
        ]));
    }
}
