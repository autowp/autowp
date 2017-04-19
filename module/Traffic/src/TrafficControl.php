<?php

namespace Autowp\Traffic;

use Autowp\Commons\Db\Table;

use DateInterval;
use DateTime;
use DateTimeZone;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;

class TrafficControl
{
    /**
     * @var Adapter
     */
    private $adapter;

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

    /**
     * @var array
     */
    private $autobanProfiles = [
        [
            'limit'  => 3000,
            'reason' => 'daily limit',
            'group'  => [],
            'time'   => 10 * 24 * 3600
        ],
        [
            'limit'  => 1000,
            'reason' => 'hourly limit',
            'group'  => ['hour'],
            'time'   => 5 * 24 * 3600
        ],
        [
            'limit'  => 300,
            'reason' => 'ten limit',
            'group'  => ['hour', 'tenminute'],
            'time'   => 24 * 3600
        ],
        [
            'limit'  => 80,
            'reason' => 'min limit',
            'group'  => ['hour', 'tenminute', 'minute'],
            'time'   => 12 * 3600
        ],
    ];

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @return TableGateway
     */
    private function getBannedTable()
    {
        if (! $this->bannedTable) {
            $this->bannedTable = new TableGateway('banned_ip', $this->adapter);
        }

        return $this->bannedTable;
    }

    /**
     * @return TableGateway
     */
    private function getWhitelistTable()
    {
        if (! $this->whitelistTable) {
            $this->whitelistTable = new TableGateway('ip_whitelist', $this->adapter);
        }

        return $this->whitelistTable;
    }

    /**
     * @return TableGateway
     */
    private function getMonitoringTable()
    {
        if (! $this->monitoringTable) {
            $this->monitoringTable = new TableGateway('ip_monitoring4', $this->adapter);
        }

        return $this->monitoringTable;
    }

    /**
     * @param string $ip
     * @param int $seconds
     * @param int|null $byUserId
     * @param string $reason
     */
    public function ban($ip, $seconds, $byUserId, $reason)
    {
        $seconds = (int)$seconds;

        if ($seconds <= 0) {
            throw new \InvalidArgumentException("Seconds must be > 0");
        }

        $reason = trim($reason);

        $table = $this->getBannedTable();

        $row = $table->select([
            'ip = INET6_ATON(?)' => $ip
        ])->current();

        $datetime = new DateTime();
        $datetime->setTimezone(new DateTimeZone(MYSQL_TIMEZONE));
        $datetime->add(new DateInterval('PT'.$seconds.'S'));
        $dateStr = $datetime->format(MYSQL_DATETIME_FORMAT);

        $data = [
            'up_to'      => $dateStr,
            'by_user_id' => $byUserId,
            'reason'     => $reason
        ];

        if (! $row) {
            $table->insert(array_replace($data, [
                'ip' => new Expression('INET6_ATON(?)', $ip)
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
        $monitoringTable = $this->getMonitoringTable();

        $group = array_merge(['ip'], $profile['group']);

        $rows = $monitoringTable->select(function (Select $select) use ($profile, $group) {
            $select
                ->columns(['ip', 'c' => new Expression('SUM(count)')])
                ->where('day_date = CURDATE()')
                ->group($group)
                ->having([
                    'c > ?' => $profile['limit']
                ]);
        });

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
    private function inWhiteListBin($ip)
    {
        return (bool)$this->getWhitelistTable()->select([
            'ip = UNHEX(?)' => bin2hex($ip)
        ])->current();
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function inWhiteList($ip)
    {
        return (bool)$this->getWhitelistTable()->select([
            'ip = INET6_ATON(?)' => (string)$ip
        ])->current();
    }

    /**
     * @return array
     */
    public function getTopData()
    {
        $bannedTable = $this->getBannedTable();
        $monitoringTable = $this->getMonitoringTable();

        $rows = $monitoringTable->select(function (Select $select) {
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
            $banRow = $bannedTable->select([
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
        $rows = $this->getWhitelistTable()->select(function (Select $select) {
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
        $this->getWhitelistTable()->delete([
            'ip = INET6_ATON(?)' => $ip
        ]);
    }

    /**
     * @param string $ip
     * @param string $description
     */
    public function addToWhitelist($ip, $description)
    {
        $banRow = $this->unban($ip);

        $table = $this->getWhitelistTable();

        $row = $table->select([
            'ip = inet6_aton(?)' => $ip
        ])->current();

        if (! $row) {
            $table->insert([
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
        $table = $this->getBannedTable();

        $row = $table->select([
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

    /**
     * @param string $ip
     */
    public function pushHit($ip)
    {
        if ($ip) {
            $table = $this->getBannedTable();

            $sql = '
                INSERT INTO ip_monitoring4 (ip, day_date, hour, tenminute, minute, count)
                VALUES (INET6_ATON(?), CURDATE(), HOUR(NOW()), FLOOR(MINUTE(NOW())/10), MINUTE(NOW()), 1)
                ON DUPLICATE KEY UPDATE count=count+1
            ';
            $stmt = $this->adapter->query($sql);
            $result = $stmt->execute([$ip]);
        }
    }

    public function autoWhitelist()
    {
        $monitoringTable = $this->getMonitoringTable();
        $whitelistTable = $this->getWhitelistTable();

        $rows = $monitoringTable->select(function (Select $select) {
            $select
                ->columns(['ip', 'count' => new Expression('SUM(count)')])
                ->where('day_date = CURDATE()')
                ->group('ip')
                ->order('count DESC')
                ->limit(1000);
        });

        foreach ($rows as $row) {
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
