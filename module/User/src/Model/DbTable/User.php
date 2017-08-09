<?php

namespace Autowp\User\Model\DbTable;

use DateInterval;
use DateTime;

use Autowp\Commons\Db\Table\Row;

use Zend_Db_Table;

class User extends Zend_Db_Table
{
    protected $_name = 'users';

    const MIN_NAME = 2;
    const MAX_NAME = 50;
    const MIN_PASSWORD = 6;
    const MAX_PASSWORD = 50;

    public function updateSpecsVolumes()
    {
        $db = $this->getAdapter();
        $pairs = $db->fetchPairs(
            $db->select()
                ->from('users', ['id', 'count(attrs_user_values.user_id)'])
                ->joinLeft('attrs_user_values', 'attrs_user_values.user_id = users.id', null)
                ->where('not users.specs_volume_valid')
                ->group('users.id')
        );

        foreach ($pairs as $userId => $volume) {
            $this->update([
                'specs_volume'       => $volume,
                'specs_volume_valid' => 1
            ], [
                'id = ?' => $userId
            ]);
        }
    }

    public function invalidateSpecsVolume(int $userId)
    {
        $this->update([
            'specs_volume_valid' => 0
        ], [
            'id = ?' => $userId
        ]);
    }

    private function getMessagingInterval($row)
    {
        $date = Row::getDateTimeByColumnType('timestamp', $row['reg_date']);

        $defaultInterval = 300;

        if (! $date) {
            return $defaultInterval;
        }

        $tenDaysBefore = (new DateTime())->sub(new DateInterval('P10D'));
        if ($tenDaysBefore > $date) {
            return $row['messaging_interval'];
        }

        return max($row['messaging_interval'], $defaultInterval);
    }

    public function getNextMessageTime(int $userId)
    {
        $row = $this->find($userId)->current();
        if (! $row) {
            return false;
        }

        $lastMessageTime = Row::getDateTimeByColumnType('timestamp', $row['last_message_time']);

        if ($lastMessageTime) {
            $messagingInterval = $this->getMessagingInterval($row);
            if ($messagingInterval) {
                $interval = new DateInterval('PT'.$messagingInterval.'S');
                return $lastMessageTime->add($interval);
            }
        }

        return false;
    }
}
