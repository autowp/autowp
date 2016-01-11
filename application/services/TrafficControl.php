<?php

class Application_Service_TrafficControl
{
    /**
     * @var Project_Db_Table
     */
    private $_bannedTable;

    /**
     * @var Project_Db_Table
     */
    private $_whitelistTable;

    /**
     * @var Project_Db_Table
     */
    private $_monitoringTable;

    /**
     * @var array
     */
    private $_autobanProfiles = [
        array(
            'limit'  => 3000,
            'reason' => 'daily limit',
            'group'  => array(),
            'time'   => 10*24*3600
        ),
        array(
            'limit'  => 1000,
            'reason' => 'hourly limit',
            'group'  => array('hour'),
            'time'   => 5*24*3600
        ),
        array(
            'limit'  => 300,
            'reason' => 'ten limit',
            'group'  => array('hour', 'tenminute'),
            'time'   => 24*3600
        ),
        array(
            'limit'  => 80,
            'reason' => 'min limit',
            'group'  => array('hour', 'tenminute', 'minute'),
            'time'   => 12*3600
        ),
    ];

    /**
     * @return Project_Db_Table
     */
    private function _getBannedTable()
    {
        if (!$this->_bannedTable) {
            $this->_bannedTable = new Project_Db_Table(array(
                'name'    => 'banned_ip',
                'primary' => 'ip'
            ));
        }

        return $this->_bannedTable;
    }

    /**
     * @return Project_Db_Table
     */
    private function _getWhitelistTable()
    {
        if (!$this->_whitelistTable) {
            $this->_whitelistTable = new Project_Db_Table(array(
                'name'    => 'ip_whitelist',
                'primary' => 'ip'
            ));
        }

        return $this->_whitelistTable;
    }

    /**
     * @return Project_Db_Table
     */
    private function _getMonitoringTable()
    {
        if (!$this->_monitoringTable) {
            $this->_monitoringTable = new Project_Db_Table(array(
                'name'    => 'ip_monitoring4',
                'primary' => ['ip', 'day_date', 'hour', 'tenminute', 'minute']
            ));
        }

        return $this->_monitoringTable;
    }

    /**
     * @param string $ip
     * @return string
     */
    private function _ip2binary($ip)
    {
        $table = $this->_getBannedTable();

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
        $table = $this->_getBannedTable();

        $row = $table->fetchRow(array(
            'ip = INET6_ATON(?)' => $ip
        ));

        $datetime = new DateTime();
        $datetime->setTimezone(new DateTimeZone(MYSQL_TIMEZONE));
        $datetime->add(new DateInterval('PT'.$seconds.'S'));
        $dateStr = $datetime->format(MYSQL_DATETIME_FORMAT);

        $data = array(
            'up_to'      => $dateStr,
            'by_user_id' => $byUserId,
            'reason'     => $reason
        );

        if (!$row) {
            $expr = $table->getAdapter()->quoteInto('INET6_ATON(?)', $ip);

            $table->insert(array_replace($data, array(
                'ip' => new Zend_Db_Expr($expr),
            )));
        } else {
            $table->update($data, array(
                'ip = INET6_ATON(?)' => $ip
            ));
        }
    }

    /**
     * @param string $ip
     */
    public function unban($ip)
    {
        $this->_getBannedTable()->delete(array(
            'ip = INET6_ATON(?)' => $ip
        ));
    }

    /**
     * @param array $profile
     * @return void
     */
    private function _autoBanByProfile(array $profile)
    {
        $table = $this->_getBannedTable();

        $db = $table->getAdapter();

        $group = array_merge(array('ip'), $profile['group']);

        $rows = $db->fetchAll(
            $db->select()
                ->from('ip_monitoring4', array('ip', 'c' => new Zend_Db_Expr('SUM(count)')))
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
        foreach ($this->_autobanProfiles as $profile) {
            $this->_autoBanByProfile($profile);
        }
    }

    /**
     * @param string $ip
     * @return bool
     */
    private function _inWhiteList($ip)
    {
        $table = $this->_getWhitelistTable();

        return (bool)$table->fetchRow(array(
            'ip = UNHEX(?)' => bin2hex($ip)
        ));
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function inWhiteList($ip)
    {
        $table = $this->_getWhitelistTable();

        return (bool)$table->fetchRow(array(
            'ip = INET6_ATON(?)' => $ip
        ));
    }

    /**
     * @return array
     */
    public function getTopData()
    {
        $bannedTable = $this->_getBannedTable();

        $sql = 'SELECT ip, INET6_NTOA(ip) as ip_text, SUM(count) AS count FROM ip_monitoring4 '.
               'WHERE day_date=CURDATE() GROUP BY ip '.
               'ORDER BY count DESC limit 50';
        $rows = $bannedTable->getAdapter()->fetchAll($sql);

        $result = [];

        foreach ($rows as $row) {
            $banRow = $bannedTable->fetchRow(array(
                'ip = unhex(?)' => bin2hex($row['ip']),
                'up_to >= NOW()'
            ));

            $result[] = array(
                'ip'        => $row['ip_text'],
                'count'     => $row['count'],
                'ban'       => $banRow ? $banRow->toArray() : null,
                'whitelist' => $this->_inWhiteList($row['ip'])
            );
        }
        unset($row);

        return $result;
    }

    /**
     * @return array
     */
    public function getWhitelistData()
    {
        $whitelistTable = $this->_getWhitelistTable();

        $db = $whitelistTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from($whitelistTable->info('name'), ['description', 'ip_text' => 'INET6_NTOA(ip)'])
                ->order('ip')
        );
        $items = array();
        foreach ($rows as $row) {
            $items[] = array(
                'ip'          => $row['ip_text'],
                'description' => $row['description']
            );
        }

        return $items;
    }

    /**
     * @param string $ip
     */
    public function deleteFromWhitelist($ip)
    {
        $whitelistTable = $this->_getWhitelistTable();

        $row = $whitelistTable->fetchRow(array(
            'ip = INET6_ATON(?)' => $ip
        ));

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

        $whitelistTable = $this->_getWhitelistTable();

        $row = $whitelistTable->fetchRow(array(
            'ip = inet6_aton(?)' => $ip
        ));

        if (!$row) {
            $expr = $whitelistTable->getAdapter()->quoteInto('inet6_aton(?)', $ip);
            $whitelistTable->insert(array(
                'ip'          => new Zend_Db_Expr($expr),
                'description' => $description
            ));
        }
    }

    /**
     * @param string $ip
     * @return boolean|array
     */
    public function getBanInfo($ip)
    {
        $table = $this->_getBannedTable();

        $row = $table->fetchRow(array(
            'ip = INET6_ATON(?)' => $ip,
            'up_to >= NOW()'
        ));

        if (!$row) {
            return false;
        }

        return array(
            'up_to'   => DateTime::createFromFormat(
                MYSQL_DATETIME_FORMAT,
                $row->up_to,
                new DateTimeZone(MYSQL_TIMEZONE)
            ),
            'user_id' => $row->by_user_id,
            'reason'  => $row->reason
        );
    }

    /**
     * @param string $ip
     */
    public function pushHit($ip)
    {
        $table = $this->_getBannedTable();

        $sql = '
            INSERT INTO ip_monitoring4 (ip, day_date, hour, tenminute, minute, count)
            VALUES (INET6_ATON(?), CURDATE(), HOUR(NOW()), FLOOR(MINUTE(NOW())/10), MINUTE(NOW()), 1)
            ON DUPLICATE KEY UPDATE count=count+1
        ';
        $table->getAdapter()->query($sql, [$ip]);
    }

    public function autoWhitelist()
    {
        $monitoringTable = $this->_getMonitoringTable();
        $whitelistTable = $this->_getWhitelistTable();
        $bannedTable = $this->_getBannedTable();

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
                    $whitelistTable->insert(array(
                        'ip'          => $ip,
                        'description' => $whitelistDesc
                    ));

                    $this->unban($ipText);

                    $monitoringTable->delete(array(
                        'ip = INET6_ATON(?)' => $ipText
                    ));

                    print ' whitelisted';
                }
            }

            print PHP_EOL;
        }
    }
}