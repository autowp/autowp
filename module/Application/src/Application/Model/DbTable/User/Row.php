<?php

namespace Application\Model\DbTable\User;

use DateInterval;
use DateTime;

class Row extends \Application\Db\Table\Row
{
    public function getCompoundName()
    {
        if ($this->deleted) {
            return 'deleted';
        }

        if ($this->name) {
            return $this->name;
        }

        if ($this->login) {
            return $this->login;
        }

        return 'user' . $this->id;
    }

    public function getMessagingInterval()
    {
        $date = $this->getDateTime('reg_date');
        $defaultInterval = 300;

        if (! $date) {
            return $defaultInterval;
        }

        $tenDaysBefore = (new DateTime())->sub(new DateInterval('P10D'));
        if ($tenDaysBefore > $date) {
            return $this->messaging_interval;
        } else {
            return max($this->messaging_interval, $defaultInterval);
        }
    }

    public function nextMessageTime()
    {
        $lastMessageTime = $this->getDateTime('last_message_time');

        if ($lastMessageTime) {
            $messagingInterval = $this->getMessagingInterval();
            if ($messagingInterval) {
                $interval = new DateInterval('PT'.$messagingInterval.'S');
                return $lastMessageTime->add($interval);
            }
        }

        return false;
    }

    public function invalidateSpecsVolume()
    {
        $this->specs_volume_valid = 0;
        $this->save();
    }
}
