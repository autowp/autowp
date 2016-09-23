<?php

namespace Application\Service;

use Application\Db\Table;

use DateInterval;
use DateTime;
use DateTimeZone;

use Zend_Db_Expr;

class TrafficControl
{
    /**
     * @var Table
     */
    private $bannedTable;

    /**
     * @var Table
     */
    private $whitelistTable;

    /**
     * @var Table
     */
    private $monitoringTable;

    /**
     * @var array
     */
    private $autobanProfiles = [
        [
            'limit'  => 3000,
            'reason' => 'daily limit',
            'group'  => [],
            'time'   => 10*24*3600
        ],
        [
            'limit'  => 1000,
            'reason' => 'hourly limit',
            'group'  => ['hour'],
            'time'   => 5*24*3600
        ],
        [
            'limit'  => 300,
            'reason' => 'ten limit',
            'group'  => ['hour', 'tenminute'],
            'time'   => 24*3600
        ],
        [
            'limit'  => 80,
            'reason' => 'min limit',
            'group'  => ['hour', 'tenminute', 'minute'],
            'time'   => 12*3600
        ],
    ];

    /**
     * @return Table
     */
    private function getBannedTable()
    {
        if (!$this->bannedTable) {
            $this->bannedTable = new Table([
                'name'    => 'banned_ip',
                'primary' => 'ip'
            ]);
        }

        return $this->bannedTable;
    }

    /**
     * @return Table
     */
    private function getWhitelistTable()
    {
        if (!$this->whitelistTable) {
            $this->whitelistTable = new Table([
                'name'    => 'ip_whitelist',
                'primary' => 'ip'
            ]);
        }

        return $this->whitelistTable;
    }

    /**
     * @return Table
     */
    private function getMonitoringTable()
    {
        if (!$this->monitoringTable) {
            $this->monitoringTable = new Table([
                'name'    => 'ip_monitoring4',
                'primary' => ['ip', 'day_date', 'hour', 'tenminute', 'minute']
            ]);
        }

        return $this->monitoringTable;
    }

    /**
     * @param string $ip
     * @return string
     */
    private function ip2binary($ip)
    {
        $table = $this->getBannedTable();

        return $table->getAdapter()->fetchOne('select INET6_ATON(?)', $ip);
    }

    /**
     * @param string $ip
     * @param int $seconds
     * @param int|null $byUserId
     * @param string $reason
     */
    public function ban($ip, $seconds, $byUserId, $reason)
    {
        $table = $this->getBannedTable();

        $row = $table->fetchRow([
            'ip = INET6_ATON(?)' => $ip
        ]);

        $datetime = new DateTime();
        $datetime->setTimezone(new DateTimeZone(MYSQL_TIMEZONE));
        $datetime->add(new DateInterval('PT'.$seconds.'S'));
        $dateStr = $datetime->format(MYSQL_DATETIME_FORMAT);

        $data = [
            'up_to'      => $dateStr,
            'by_user_id' => $byUserId,
            'reason'     => $reason
        ];

        if (!$row) {
            $expr = $table->getAdapter()->quoteInto('INET6_ATON(?)', $ip);

            $table->insert(array_replace($data, [
                'ip' => new Zend_Db_Expr($expr),
            ]));
        } else {
            $table->update($data, [
                'ip = INET6_ATON(?)' => $ip
            ]);
        }
    }

    /**
     * @param string $ip
     */
    public function unban($ip)
    {
        $this->getBannedTable()->delete([
            'ip = INET6_ATON(?)' => $ip
        ]);
    }

    /**
     * @param array $profile
     * @return void
     */
    private function autoBanByProfile(array $profile)
    {
        $table = $this->getBannedTable();

        $db = $table->getAdapter();

        $group = array_merge(['ip'], $profile['group']);

        $rows = $db->fetchAll(
            $db->select()
                ->from('ip_monitoring4', ['ip', 'c' => new Zend_Db_Expr('SUM(count)')])
                ->where('day_date = CURDATE()')
                ->group($group)
                ->having('c > ?', $profile['limit'])
                );
        foreach ($rows as $row) {
            $ip = inet_ntop($row['ip']);

            if ($this->inWhiteList($ip)) {
                continue;
            }

            print $profile['reason'] . ' ' . $ip . PHP_EOL;

            $this->ban($ip, $profile['time'], 9, $profile['reason']);
        }
    }

    /**
     * @return void
     */
    public function autoBan()
    {
        foreach ($this->autobanProfiles as $profile) {
            $this->autoBanByProfile($profile);
        }
    }

    /**
     * @param string $ip
     * @return bool
     */
    private function _inWhiteList($ip)
    {
        $table = $this->getWhitelistTable();

        return (bool)$table->fetchRow([
            'ip = UNHEX(?)' => bin2hex($ip)
        ]);
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function inWhiteList($ip)
    {
        $table = $this->getWhitelistTable();

        return (bool)$table->fetchRow([
            'ip = INET6_ATON(?)' => $ip
        ]);
    }

    /**
     * @return array
     */
    public function getTopData()
    {
        $bannedTable = $this->getBannedTable();

        $sql = 'SELECT ip, INET6_NTOA(ip) as ip_text, SUM(count) AS count FROM ip_monitoring4 '.
               'WHERE day_date=CURDATE() GROUP BY ip '.
               'ORDER BY count DESC limit 50';
        $rows = $bannedTable->getAdapter()->fetchAll($sql);

        $result = [];

        foreach ($rows as $row) {
            $banRow = $bannedTable->fetchRow([
                'ip = unhex(?)' => bin2hex($row['ip']),
                'up_to >= NOW()'
            ]);

            $result[] = [
                'ip'        => $row['ip_text'],
                'count'     => $row['count'],
                'ban'       => $banRow ? $banRow->toArray() : null,
                'whitelist' => $this->_inWhiteList($row['ip'])
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
        $whitelistTable = $this->getWhitelistTable();

        $db = $whitelistTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from($whitelistTable->info('name'), ['description', 'ip_text' => 'INET6_NTOA(ip)'])
                ->order('ip')
        );
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
        $whitelistTable = $this->getWhitelistTable();

        $row = $whitelistTable->fetchRow([
            'ip = INET6_ATON(?)' => $ip
        ]);

        if ($row) {
            $row->delete();
        }
    }

    /**
     * @param string $ip
     * @param string $description
     */
    public function addToWhitelist($ip, $description)
    {
        $banRow = $this->unban($ip);

        $whitelistTable = $this->getWhitelistTable();

        $row = $whitelistTable->fetchRow([
            'ip = inet6_aton(?)' => $ip
        ]);

        if (!$row) {
            $expr = $whitelistTable->getAdapter()->quoteInto('inet6_aton(?)', $ip);
            $whitelistTable->insert([
                'ip'          => new Zend_Db_Expr($expr),
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
        $table = $this->getBannedTable();

        $row = $table->fetchRow([
            'ip = INET6_ATON(?)' => $ip,
            'up_to >= NOW()'
        ]);

        if (!$row) {
            return false;
        }

        return [
            'up_to'   => DateTime::createFromFormat(
                MYSQL_DATETIME_FORMAT,
                $row->up_to,
                new DateTimeZone(MYSQL_TIMEZONE)
            ),
            'user_id' => $row->by_user_id,
            'reason'  => $row->reason
        ];
    }

    /**
     * @param string $ip
     */
    public function pushHit($ip)
    {
        $table = $this->getBannedTable();

        $sql = '
            INSERT INTO ip_monitoring4 (ip, day_date, hour, tenminute, minute, count)
            VALUES (INET6_ATON(?), CURDATE(), HOUR(NOW()), FLOOR(MINUTE(NOW())/10), MINUTE(NOW()), 1)
            ON DUPLICATE KEY UPDATE count=count+1
        ';
        $table->getAdapter()->query($sql, [$ip]);
    }

    public function autoWhitelist()
    {
        $monitoringTable = $this->getMonitoringTable();
        $whitelistTable = $this->getWhitelistTable();
        $bannedTable = $this->getBannedTable();

        $db = $monitoringTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from($monitoringTable->info('name'), ['ip', 'count' => 'SUM(count)'])
                ->where('day_date = CURDATE()')
                ->group('ip')
                ->order('count DESC')
                ->limit(1000)
        );

        foreach ($rows as &$row) {
            $ip = $row['ip'];
            $ipText = inet_ntop($ip);

            print $ipText. ': ';


            if ($this->inWhitelist($ipText)) {
                print 'whitelist, skip';
            } else {

                $whitelist = false;
                $whitelistDesc = '';

                $host = gethostbyaddr($ipText);

                if ($host === false) {
                    $host = 'unknown.host';
                }

                print $host;

                $msnHost = 'msnbot-' . str_replace('.', '-', $ipText) . '.search.msn.com';
                $yandexComHost = 'spider-'.str_replace('.', '-', $ipText).'.yandex.com';
                $mailHostPattern = '/^fetcher[0-9]-[0-9]\.p\.mail.ru$/';
                $googlebotHost = 'crawl-' . str_replace('.', '-', $ipText) . '.googlebot.com';
                if ($host == $msnHost) {
                    $whitelist = true;
                    $whitelistDesc = 'msnbot autodetect';
                } if ($host == $yandexComHost) {
                    $whitelist = true;
                    $whitelistDesc = 'yandex.com autodetect';
                } if ($host == $googlebotHost) {
                    $whitelist = true;
                    $whitelistDesc = 'googlebot autodetect';
                } if (preg_match($mailHostPattern, $host)) {
                    $whitelist = true;
                    $whitelistDesc = 'mail.ru autodetect';
                }


                if ($whitelist) {
                    $whitelistTable->insert([
                        'ip'          => $ip,
                        'description' => $whitelistDesc
                    ]);

                    $this->unban($ipText);

                    $monitoringTable->delete([
                        'ip = INET6_ATON(?)' => $ipText
                    ]);

                    print ' whitelisted';
                }
            }

            print PHP_EOL;
        }
    }

    public function gc()
    {
        $count = $this->getMonitoringTable()->delete([
            'day_date < CURDATE()'
        ]);

        $count += $this->getBannedTable()->delete(
            'up_to < NOW()'
        );

        return $count;
    }
}