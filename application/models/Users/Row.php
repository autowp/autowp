<?php

class Users_Row extends Project_Db_Table_Row
{
    /**
     * @deprecated
     * @param bool $absolute
     * @return string
     */
    public function getAboutUrl($absolute = false)
    {
        return ($absolute ? HOST : '/').'users/' . ($this->identity ? $this->identity : 'user'.$this->id);
    }

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
        $date = $this->getDate('reg_date');
        $defaultInterval = 300;

        if (!$date)
            return $defaultInterval;

        if (Zend_Date::now()->subDay(10)->isLater($date)) {
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