<?php

use Application\Db\Table\Row;

class User_Row extends Row
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

        return 'user'.$this->id;
    }

    public function getMessagingInterval()
    {
        $date = $this->getDateTime('reg_date');
        $defaultInterval = 300;

        if (!$date) {
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

    public function refreshPicturesRatio()
    {
        $votes = new Votes();

        $value = $votes->getAdapter()->fetchOne(
            $votes->select()
                ->from($votes, new Zend_Db_Expr('SUM(summary)/SUM(count)'))
                ->join('pictures', 'votes.picture_id=pictures.id', null)
                ->where('pictures.owner_id = ?', $this->id)

        );

        if ($value <= 0) {
            $value = null;
        }

        $this->pictures_ratio = $value;
        $this->save();
    }

    public function invalidateSpecsVolume()
    {
        $this->specs_volume_valid = 0;
        $this->save();
    }
}