<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Json\Json;

use Application\Service\RabbitMQ;

class Referer
{
    /**
     * @var RabbitMQ
     */
    private $rabbitmq;

    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var TableGateway
     */
    private $whitelistTable;

    /**
     * @var TableGateway
     */
    private $blacklistTable;

    public function __construct(
        RabbitMQ $rabbitmq,
        TableGateway $table,
        TableGateway $whitelistTable,
        TableGateway $blacklistTable
    ) {
        $this->rabbitmq = $rabbitmq;
        $this->table = $table;
        $this->whitelistTable = $whitelistTable;
        $this->blacklistTable = $blacklistTable;
    }

    public function addUrl(string $url, string $accept): void
    {
        $this->rabbitmq->send('hotlink', Json::encode([
            'url'       => $url,
            'accept'    => $accept,
            'timestamp' => (new \DateTime())->format(\DateTime::RFC3339)
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
        return (bool)$this->whitelistTable->select([
            'host' => $host
        ])->current();
    }

    public function isHostBlacklisted(string $host): bool
    {
        return (bool)$this->blacklistTable->select([
            'host' => $host
        ])->current();
    }

    public function isUrlBlacklisted(string $url): bool
    {
        $host = @parse_url($url, PHP_URL_HOST);
        if ($host) {
            return $this->isHostBlacklisted($host);
        }

        return false;
    }

    public function addToWhitelist(string $host)
    {
        $this->blacklistTable->delete([
            'host = ?' => $host
        ]);

        $this->whitelistTable->insert([
            'host' => $host
        ]);
    }

    public function addToBlacklist(string $host)
    {
        $this->whitelistTable->delete([
            'host = ?' => $host
        ]);

        $this->blacklistTable->insert([
            'host' => $host
        ]);
    }

    public function flushHost(string $host)
    {
        $this->table->delete([
            'host = ?' => $host
        ]);
    }

    public function flush()
    {
        $this->table->delete([]);
    }

    public function getData(): array
    {
        $select = new Sql\Select($this->table->getTable());
        $select->columns(['host', 'count' => new Sql\Expression('SUM(count)')])
            ->where(['last_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)'])
            ->group('host')
            ->order('count desc')
            ->limit(100);

        $rows = $this->table->selectWith($select);

        $items = [];
        foreach ($rows as $row) {
            $select = new Sql\Select($this->table->getTable());
            $select
                ->where([
                    'host' => (string)$row['host'],
                    'last_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
                ])
                ->order('count desc')
                ->limit(20);

            $links = [];
            foreach ($this->table->selectWith($select) as $link) {
                $links[] = [
                    'url'    => $link['url'],
                    'count'  => $link['count'],
                    'accept' => $link['accept'],
                ];
            }

            $items[] = [
                'host'        => $row['host'],
                'count'       => (int)$row['count'],
                'whitelisted' => $this->isHostWhitelisted($row['host']),
                'blacklisted' => $this->isHostBlacklisted($row['host']),
                'links'       => $links
            ];
        }

        return $items;
    }
}
