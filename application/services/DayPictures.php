<?php

class Application_Service_DayPictures
{
    /**
     * @var string
     */
    protected $_timezone = 'UTC';

    /**
     * @var string
     */
    protected $_dbTimezone = 'UTC';

    /**
     * @var Zend_Db_Table_Select
     */
    protected $_select = null;

    /**
     * @var string
     */
    protected $_orderColumn = null;

    /**
     * @var string
     */
    protected $_externalDateFormat = 'yyyy-MM-dd';

    /**
     * @var string
     */
    protected $_dbDateTimeFormat = MYSQL_DATETIME;

    /**
     * @var Zend_Date
     */
    protected $_currentDate = null;

    /**
     * @var Zend_Date
     */
    protected $_prevDate = null;

    /**
     * @var Zend_Date
     */
    protected $_nextDate = null;

    /**
     * @var Zend_Date
     */
    protected $_minDate = null;

    /**
     * @var Zend_Paginaotr
     */
    protected $_paginator = null;

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     * @return Application_Service_DayPictures
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                $this->_raise("Unexpected option '$key'");
            }
        }

        return $this;
    }

    /**
     * @param string $timezone
     * @return Application_Service_DayPictures
     */
    public function setTimeZone($timezone)
    {
        $this->_timezone = (string)$timezone;

        return $this->_reset();
    }

    /**
     * @param string $timezone
     * @return Application_Service_DayPictures
     */
    public function setDbTimeZone($timezone)
    {
        $this->_dbTimezone = (string)$timezone;

        return $this->_reset();
    }

    /**
     * @param Zend_Db_Table_Select $select
     * @return Application_Service_DayPictures
     */
    public function setSelect(Zend_Db_Table_Select $select)
    {
        $this->_select = $select;

        return $this->_reset();
    }

    /**
     * @param Zend_Date $date
     * @return Application_Service_DayPictures
     */
    public function setMinDate(Zend_Date $date)
    {
        $this->_minDate = $date;

        return $this;
    }

    /**
     * @param string $column
     * @return Application_Service_DayPictures
     */
    public function setOrderColumn($column)
    {
        $this->_orderColumn = $column;

        return $this->_reset();
    }

    /**
     * @return bool
     */
    public function haveCurrentDate()
    {
        return (bool)$this->_currentDate;
    }

    /**
     * @return string
     */
    public function getCurrentDate()
    {
        return $this->_currentDate;
    }

    /**
     * @return string
     */
    public function getCurrentDateStr()
    {
        return $this->_currentDate
            ? $this->_currentDate->get($this->_externalDateFormat)
            : false;
    }

    /**
     * @return int
     */
    public function getCurrentDateCount()
    {
        return $this->_currentDate ? $this->_dateCount($this->_currentDate) : 0;
    }

    /**
     * @param string|Zend_Date $date
     * @throws Exception
     * @return Application_Service_DayPictures
     */
    public function setCurrentDate($date)
    {
        $dateObj = null;

        if (!empty($date)) {
            if (is_string($date)) {
                $dateStr = $date . ' ' . $this->_timezone;
                $format = $this->_externalDateFormat . ' zzzz';

                $dateObj = new Zend_Date($dateStr, $format);
            } elseif ($date instanceof Zend_Date) {
                $dateObj = $date;
            } else {
                throw new Exception("Unexpected type of date");
            }
        }

        if ($dateObj) {
            $dateObj->setTimeZone($this->_timezone);
        }

        $this->_currentDate = $dateObj;

        return $this->_reset();
    }

    /**
     * @return boolean
     */
    public function haveCurrentDayPictures()
    {
        if (!$this->_currentDate) {
            return false;
        }

        $paginator = $this->getPaginator();
        $count = $paginator ? $paginator->getTotalItemCount() : 0;

        return $count > 0;
    }

    /**
     * @return string|null
     */
    public function getLastDateStr()
    {
        $select = $this->_selectClone()
            ->order($this->_orderColumn . ' desc');

        $lastPicture = $select->getTable()->fetchRow($select);
        if (!$lastPicture) {
            return null;
        }

        $lastDate = $lastPicture->getDate($this->_orderColumn);
        if (!$lastDate) {
            return null;
        }

        return $lastDate
            ->setTimeZone($this->_timezone)
            ->get($this->_externalDateFormat);
    }

    /**
     * @return Application_Service_DayPictures
     */
    protected function _calcPrevDate()
    {
        if (!$this->_currentDate) {
            return $this;
        }

        if ($this->_prevDate === null) {

            $column = $this->_quotedOrderColumn();

            $select = $this->_selectClone()
                ->where($column . ' < ?', $this->_startOfDayDbValue($this->_currentDate))
                ->order($this->_orderColumn . ' DESC');

            if ($this->_minDate) {
                $select->where($column . ' >= ?', $this->_startOfDayDbValue($this->_minDate));
            }

            $prevDatePicture = $select->getTable()->fetchRow($select);

            $prevDate = false;
            if ($prevDatePicture) {
                $date = $prevDatePicture->getDate($this->_orderColumn);
                if ($date) {
                    $prevDate = $date;
                }
            }

            if ($prevDate) {
                $this->_prevDate = $prevDate->setTimezone($this->_timezone);
            } else {
                $this->_prevDate = false;
            }
        }

        return $this;
    }

    /**
     * @return false|Zend_Date
     */
    public function getPrevDate()
    {
        $this->_calcPrevDate();

        return $this->_prevDate;
    }

    /**
     * @return string
     */
    public function getPrevDateStr()
    {
        $this->_calcPrevDate();

        return $this->_prevDate
            ? $this->_prevDate->get($this->_externalDateFormat)
            : false;
    }

    /**
     * @return int
     */
    public function getPrevDateCount()
    {
        $this->_calcPrevDate();

        return $this->_prevDate ? $this->_dateCount($this->_prevDate) : 0;
    }

    /**
     * @return Application_Service_DayPictures
     */
    protected function _calcNextDate()
    {
        if (!$this->_currentDate) {
            return $this;
        }

        if ($this->_nextDate === null) {

            $column = $this->_quotedOrderColumn();

            $select = $this->_selectClone()
                ->where($column . ' > ?', $this->_endOfDayDbValue($this->_currentDate))
                ->order($this->_orderColumn);

            $nextDatePicture = $select->getTable()->fetchRow($select);

            $nextDate = false;
            if ($nextDatePicture) {
                $date = $nextDatePicture->getDate($this->_orderColumn);
                if ($date) {
                    $nextDate = $date;
                }
            }

            if ($nextDate) {
                $this->_nextDate = $nextDate->setTimezone($this->_timezone);
            } else {
                $this->_nextDate = false;
            }
        }

        return $this;
    }

    /**
     * @return false|Zend_Date
     */
    public function getNextDate()
    {
        $this->_calcNextDate();

        return $this->_nextDate;
    }

    /**
     * @return string
     */
    public function getNextDateStr()
    {
        $this->_calcNextDate();

        return $this->_nextDate
            ? $this->_nextDate->get($this->_externalDateFormat)
            : false;
    }

    /**
     * @return int
     */
    public function getNextDateCount()
    {
        $this->_calcNextDate();

        return $this->_nextDate ? $this->_dateCount($this->_nextDate) : 0;
    }

    /**
     * @return Zend_Paginator|false
     */
    public function getPaginator()
    {
        if (!$this->_currentDate) {
            return false;
        }

        if ($this->_paginator === null) {

            $select = $this->getCurrentDateSelect();

            $this->_paginator = Zend_Paginator::factory($select);
        }

        return $this->_paginator;
    }

    /**
     * @param Zend_Date $date
     * @return int
     */
    protected function _dateCount(Zend_Date $date)
    {
        $column = $this->_quotedOrderColumn();

        $select = $this->_selectClone()
            ->where($column . ' >= ?', $this->_startOfDayDbValue($date))
            ->where($column . ' <= ?', $this->_endOfDayDbValue($date));

        return Zend_Paginator::factory($select)
            ->getTotalItemCount();
    }

    /**
     * @return Application_Service_DayPictures
     */
    protected function _reset()
    {
        $this->_nextDate = null;
        $this->_prevDate = null;
        $this->_paginator = null;

        return $this;
    }

    /**
     * @param Zend_Date $date
     * @return Zend_Date
     */
    protected function _endOfDay(Zend_Date $date)
    {
        $d = clone $date;
        return $d
            ->setHour(23)
            ->setMinute(59)
            ->setSecond(59);
    }

    /**
     * @param Zend_Date $date
     * @return Zend_Date
     */
    protected function _startOfDay(Zend_Date $date)
    {
        $d = clone $date;
        return $d
            ->setHour(0)
            ->setMinute(0)
            ->setSecond(0);
    }

    /**
     * @param Zend_Date $date
     * @return string
     */
    protected function _startOfDayDbValue(Zend_Date $date)
    {
        $d = $this->_startOfDay($date)->setTimezone($this->_dbTimezone);
        return $d->get($this->_dbDateTimeFormat);
    }

    /**
     * @param Zend_Date $date
     * @return string
     */
    protected function _endOfDayDbValue(Zend_Date $date)
    {
        $d = $this->_endOfDay($date)->setTimezone($this->_dbTimezone);
        return $d->get($this->_dbDateTimeFormat);
    }

    /**
     * @return Zend_Db_Table_Select
     */
    protected function _selectClone()
    {
        return clone $this->_select;
    }

    /**
     * @return Zend_Db_Table_Select
     */
    public function getCurrentDateSelect()
    {
        $column = $this->_quotedOrderColumn();

        $select = $this->_selectClone()
            ->where($column . ' >= ?', $this->_startOfDayDbValue($this->_currentDate))
            ->where($column . ' <= ?', $this->_endOfDayDbValue($this->_currentDate))
            ->order($this->_orderColumn . ' DESC');

        if ($this->_minDate) {
            $select->where($column . ' >= ?', $this->_startOfDayDbValue($this->_minDate));
        }

        return $select;
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function _quotedOrderColumn()
    {
        if (!$this->_orderColumn) {
            throw new Exception('Order column not configured');
        }

        $db = $this->_select->getAdapter();

        $tableName = $this->_select->getTable()->info('name');

        return $db->quoteIdentifier($tableName . '.' . $this->_orderColumn);
    }
}