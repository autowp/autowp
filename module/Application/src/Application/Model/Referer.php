<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

class Referer
{
    const MAX_URL = 1000;
    const MAX_ACCEPT = 1000;

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
        TableGateway $table,
        TableGateway $whitelistTable,
        TableGateway $blacklistTable
    ) {
        $this->table = $table;
        $this->whitelistTable = $whitelistTable;
        $this->blacklistTable = $blacklistTable;
    }

    public function addUrl($url, $accept)
    {
        $host = @parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return;
        }

        $whitelisted = $this->isHostWhitelisted($host);

        if ($whitelisted) {
            return;
        }

        if (mb_strlen($url) > self::MAX_URL) {
            $url = mb_substr($url, 0, self::MAX_URL);
        }

        $adapter = $this->table->getAdapter();
        $stmt = $adapter->query('
            insert into referer (host, url, count, last_date, accept)
            values (?, ?, 1, NOW(), LEFT(?, ?))
            on duplicate key
            update count=count+1, host=VALUES(host), last_date=VALUES(last_date), accept=VALUES(accept)
        ', $adapter::QUERY_MODE_PREPARE);
        $stmt->execute([$host, $url, $accept, self::MAX_ACCEPT]);
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

    public function isUrlWhitelisted(string $url): bool
    {
        $host = @parse_url($url, PHP_URL_HOST);
        if ($host) {
            return $this->isHostWhitelisted($host);
        }

        return false;
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

            $items[] = [
                'host'        => $row['host'],
                'count'       => (int)$row['count'],
                'whitelisted' => $this->isHostWhitelisted($row['host']),
                'blacklisted' => $this->isHostBlacklisted($row['host']),
                'links'       => $this->table->selectWith($select)
            ];
        }

        return $items;
    }

    public function garbageCollect(): int
    {
        return $this->table->delete([
            'last_date < DATE_SUB(NOW(), INTERVAL 1 DAY)'
        ]);
    }
}
