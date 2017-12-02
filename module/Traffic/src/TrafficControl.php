<?php

namespace Autowp\Traffic;

use DateInterval;
use DateTime;
use DateTimeZone;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;

class TrafficControl
{
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
            'limit'  => 3500,
            'reason' => 'daily limit',
            'group'  => [],
            'time'   => 10 * 24 * 3600
        ],
        [
            'limit'  => 1500,
            'reason' => 'hourly limit',
            'group'  => ['hour'],
            'time'   => 5 * 24 * 3600
        ],
        [
            'limit'  => 450,
            'reason' => 'ten min limit',
            'group'  => ['hour', 'tenminute'],
            'time'   => 24 * 3600
        ],
        [
            'limit'  => 150,
            'reason' => 'min limit',
            'group'  => ['hour', 'tenminute', 'minute'],
            'time'   => 12 * 3600
        ],
    ];

    public function __construct(
        TableGateway $bannedTable,
        TableGateway $whitelistTable,
        TableGateway $monitoringTable
    ) {
        $this->bannedTable = $bannedTable;
        $this->whitelistTable = $whitelistTable;
        $this->monitoringTable = $monitoringTable;
    }

    /**
     * @param string $ip
     * @param int $seconds
     * @param int|null $byUserId
     * @param string $reason
     */
    public function ban(string $ip, int $seconds, $byUserId, $reason)
    {
        $seconds = (int)$seconds;

        if ($seconds <= 0) {
            throw new \InvalidArgumentException("Seconds must be > 0");
        }

        $reason = trim($reason);

        $row = $this->bannedTable->select([
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
            $this->bannedTable->insert(array_replace($data, [
                'ip' => new Expression('INET6_ATON(?)', $ip)
            ]));
        } else {
            $this->bannedTable->update($data, [
                'ip = INET6_ATON(?)' => $ip
            ]);
        }
    }

    /**
     * @param string $ip
     */
    public function unban(string $ip)
    {
        $this->bannedTable->delete([
            'ip = INET6_ATON(?)' => $ip
        ]);
    }

    /**
     * @param array $profile
     * @return void
     */
    private function autoBanByProfile(array $profile)
    {
        $group = array_merge(['ip'], $profile['group']);

        $rows = $this->monitoringTable->select(function (Select $select) use ($profile, $group) {
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

            /*if ($this->inWhiteList($ip)) {
                continue;
            }*/

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

    /**
     * @param string $ip
     */
    public function pushHit($ip)
    {
        if ($ip) {
            $sql = '
                INSERT INTO ip_monitoring4 (ip, day_date, hour, tenminute, minute, count)
                VALUES (INET6_ATON(?), CURDATE(), HOUR(NOW()), FLOOR(MINUTE(NOW())/10), MINUTE(NOW()), 1)
                ON DUPLICATE KEY UPDATE count=count+1
            ';
            $stmt = $this->monitoringTable->getAdapter()->query($sql);
            $stmt->execute([$ip]);
        }
    }

    public function autoWhitelist()
    {
        $rows = $this->monitoringTable->select(function (Select $select) {
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
                    $this->whitelistTable->insert([
                        'ip'          => $ip,
                        'description' => $whitelistDesc
                    ]);

                    $this->unban($ipText);

                    $this->monitoringTable->delete([
                        'ip = INET6_ATON(?)' => $ipText
                    ]);

                    print ' whitelisted';
                }
            }

            print PHP_EOL;
        }
    }

    public function garbageCollect()
    {
        $count = $this->monitoringTable->delete([
            'day_date < CURDATE()'
        ]);

        $count += $this->bannedTable->delete(
            'up_to < NOW()'
        );

        return $count;
    }
}
