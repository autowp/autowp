<?php

namespace Application\Service;

use DateTime;
use DateTimeZone;
use Exception;
use Zend_Db_Table_Select;
use Zend_Paginator;


class DayPictures
{
    const DEFAULT_TIMEZONE = 'UTC';

    /**
     * @var DateTimeZone
     */
    private $_timezone = 'UTC';

    /**
     * @var DateTimeZone
     */
    private $_dbTimezone = 'UTC';

    /**
     * @var Zend_Db_Table_Select
     */
    private $_select = null;

    /**
     * @var string
     */
    private $_orderColumn = null;

    /**
     * @var string
     */
    private $_externalDateFormat = 'Y-m-d';

    /**
     * @var string
     */
    private $_dbDateTimeFormat = MYSQL_DATETIME_FORMAT;

    /**
     * @var DateTime
     */
    private $_currentDate = null;

    /**
     * @var DateTime
     */
    private $_prevDate = null;

    /**
     * @var DateTime
     */
    private $_nextDate = null;

    /**
     * @var DateTime
     */
    private $_minDate = null;

    /**
     * @var Zend_Paginator
     */
    private $_paginator = null;

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->_timezone = new DateTimeZone(self::DEFAULT_TIMEZONE);
        $this->_dbTimezone = new DateTimeZone(self::DEFAULT_TIMEZONE);

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
        $this->_timezone = new DateTimeZone($timezone);

        return $this->_reset();
    }

    /**
     * @param string $timezone
     * @return Application_Service_DayPictures
     */
    public function setDbTimeZone($timezone)
    {
        $this->_dbTimezone = new DateTimeZone($timezone);

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
     * @param DateTime $date
     * @return Application_Service_DayPictures
     */
    public function setMinDate(DateTime $date)
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
            ? $this->_currentDate->format($this->_externalDateFormat)
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
     * @param string|DateTime $date
     * @throws Exception
     * @return Application_Service_DayPictures
     */
    public function setCurrentDate($date)
    {
        $dateObj = null;

        if (!empty($date)) {
            if (is_string($date)) {
                $dateObj = DateTime::createFromFormat($this->_externalDateFormat, $date, $this->_timezone);
            } elseif ($date instanceof DateTime) {
                $dateObj = $date;
                $dateObj->setTimeZone($this->_timezone);
            } else {
                throw new Exception("Unexpected type of date");
            }
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

        $lastDate = $lastPicture->getDateTime($this->_orderColumn);
        if (!$lastDate) {
            return null;
        }

        return $lastDate
            ->setTimeZone($this->_timezone)
            ->format($this->_externalDateFormat);
    }

    /**
     * @return Application_Service_DayPictures
     */
    private function _calcPrevDate()
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
                $date = $prevDatePicture->getDateTime($this->_orderColumn);
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
     * @return false|DateTime
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
            ? $this->_prevDate->format($this->_externalDateFormat)
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
    private function _calcNextDate()
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
                $date = $nextDatePicture->getDateTime($this->_orderColumn);
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
     * @return false|DateTime
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
            ? $this->_nextDate->format($this->_externalDateFormat)
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
     * @param DateTime $date
     * @return int
     */
    private function _dateCount(DateTime $date)
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
    private function _reset()
    {
        $this->_nextDate = null;
        $this->_prevDate = null;
        $this->_paginator = null;

        return $this;
    }

    /**
     * @param DateTime $date
     * @return DateTime
     */
    private function _endOfDay(DateTime $date)
    {
        $d = clone $date;
        return $d->setTime(23, 59, 59);
    }

    /**
     * @param DateTime $date
     * @return DateTime
     */
    private function _startOfDay(DateTime $date)
    {
        $d = clone $date;
        return $d->setTime(0, 0, 0);
    }

    /**
     * @param DateTime $date
     * @return string
     */
    private function _startOfDayDbValue(DateTime $date)
    {
        $d = $this->_startOfDay($date)->setTimezone($this->_dbTimezone);
        return $d->format($this->_dbDateTimeFormat);
    }

    /**
     * @param DateTime $date
     * @return string
     */
    private function _endOfDayDbValue(DateTime $date)
    {
        $d = $this->_endOfDay($date)->setTimezone($this->_dbTimezone);
        return $d->format($this->_dbDateTimeFormat);
    }

    /**
     * @return Zend_Db_Table_Select
     */
    private function _selectClone()
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
    private function _quotedOrderColumn()
    {
        if (!$this->_orderColumn) {
            throw new Exception('Order column not configured');
        }

        $db = $this->_select->getAdapter();

        $tableName = $this->_select->getTable()->info('name');

        return $db->quoteIdentifier($tableName . '.' . $this->_orderColumn);
    }
}