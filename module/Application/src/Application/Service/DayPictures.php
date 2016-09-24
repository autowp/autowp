<?php

namespace Application\Service;

use DateTime;
use DateTimeZone;
use Exception;
use Zend_Db_Table_Select;

use Application\Paginator\Adapter\Zend1DbTableSelect;

class DayPictures
{
    const DEFAULT_TIMEZONE = 'UTC';

    /**
     * @var DateTimeZone
     */
    private $timezone = 'UTC';

    /**
     * @var DateTimeZone
     */
    private $dbTimezone = 'UTC';

    /**
     * @var Zend_Db_Table_Select
     */
    private $select = null;

    /**
     * @var string
     */
    private $orderColumn = null;

    /**
     * @var string
     */
    private $externalDateFormat = 'Y-m-d';

    /**
     * @var string
     */
    private $dbDateTimeFormat = MYSQL_DATETIME_FORMAT;

    /**
     * @var DateTime
     */
    private $currentDate = null;

    /**
     * @var DateTime
     */
    private $prevDate = null;

    /**
     * @var DateTime
     */
    private $nextDate = null;

    /**
     * @var DateTime
     */
    private $_minDate = null;

    /**
     * @var \Zend\Paginator\Paginator
     */
    private $paginator;

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->timezone = new DateTimeZone(self::DEFAULT_TIMEZONE);
        $this->dbTimezone = new DateTimeZone(self::DEFAULT_TIMEZONE);

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
        $this->timezone = new DateTimeZone($timezone);

        return $this->reset();
    }

    /**
     * @param string $timezone
     * @return Application_Service_DayPictures
     */
    public function setDbTimeZone($timezone)
    {
        $this->dbTimezone = new DateTimeZone($timezone);

        return $this->reset();
    }

    /**
     * @param Zend_Db_Table_Select $select
     * @return Application_Service_DayPictures
     */
    public function setSelect(Zend_Db_Table_Select $select)
    {
        $this->select = $select;

        return $this->reset();
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
        $this->orderColumn = $column;

        return $this->reset();
    }

    /**
     * @return bool
     */
    public function haveCurrentDate()
    {
        return (bool)$this->currentDate;
    }

    /**
     * @return string
     */
    public function getCurrentDate()
    {
        return $this->currentDate;
    }

    /**
     * @return string
     */
    public function getCurrentDateStr()
    {
        return $this->currentDate
            ? $this->currentDate->format($this->externalDateFormat)
            : false;
    }

    /**
     * @return int
     */
    public function getCurrentDateCount()
    {
        return $this->currentDate ? $this->dateCount($this->currentDate) : 0;
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
                $dateObj = DateTime::createFromFormat($this->externalDateFormat, $date, $this->timezone);
            } elseif ($date instanceof DateTime) {
                $dateObj = $date;
                $dateObj->setTimeZone($this->timezone);
            } else {
                throw new Exception("Unexpected type of date");
            }
        }

        $this->currentDate = $dateObj;

        return $this->reset();
    }

    /**
     * @return boolean
     */
    public function haveCurrentDayPictures()
    {
        if (!$this->currentDate) {
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
        $select = $this->selectClone()
            ->order($this->orderColumn . ' desc');

        $lastPicture = $select->getTable()->fetchRow($select);
        if (!$lastPicture) {
            return null;
        }

        $lastDate = $lastPicture->getDateTime($this->orderColumn);
        if (!$lastDate) {
            return null;
        }

        return $lastDate
            ->setTimeZone($this->timezone)
            ->format($this->externalDateFormat);
    }

    /**
     * @return Application_Service_DayPictures
     */
    private function calcPrevDate()
    {
        if (!$this->currentDate) {
            return $this;
        }

        if ($this->prevDate === null) {

            $column = $this->quotedOrderColumn();

            $select = $this->selectClone()
                ->where($column . ' < ?', $this->startOfDayDbValue($this->currentDate))
                ->order($this->orderColumn . ' DESC');

            if ($this->_minDate) {
                $select->where($column . ' >= ?', $this->startOfDayDbValue($this->_minDate));
            }

            $prevDatePicture = $select->getTable()->fetchRow($select);

            $prevDate = false;
            if ($prevDatePicture) {
                $date = $prevDatePicture->getDateTime($this->orderColumn);
                if ($date) {
                    $prevDate = $date;
                }
            }

            if ($prevDate) {
                $this->prevDate = $prevDate->setTimezone($this->timezone);
            } else {
                $this->prevDate = false;
            }
        }

        return $this;
    }

    /**
     * @return false|DateTime
     */
    public function getPrevDate()
    {
        $this->calcPrevDate();

        return $this->prevDate;
    }

    /**
     * @return string
     */
    public function getPrevDateStr()
    {
        $this->calcPrevDate();

        return $this->prevDate
            ? $this->prevDate->format($this->externalDateFormat)
            : false;
    }

    /**
     * @return int
     */
    public function getPrevDateCount()
    {
        $this->calcPrevDate();

        return $this->prevDate ? $this->dateCount($this->prevDate) : 0;
    }

    /**
     * @return Application_Service_DayPictures
     */
    private function calcNextDate()
    {
        if (!$this->currentDate) {
            return $this;
        }

        if ($this->nextDate === null) {

            $column = $this->quotedOrderColumn();

            $select = $this->selectClone()
                ->where($column . ' > ?', $this->endOfDayDbValue($this->currentDate))
                ->order($this->orderColumn);

            $nextDatePicture = $select->getTable()->fetchRow($select);

            $nextDate = false;
            if ($nextDatePicture) {
                $date = $nextDatePicture->getDateTime($this->orderColumn);
                if ($date) {
                    $nextDate = $date;
                }
            }

            if ($nextDate) {
                $this->nextDate = $nextDate->setTimezone($this->timezone);
            } else {
                $this->nextDate = false;
            }
        }

        return $this;
    }

    /**
     * @return false|DateTime
     */
    public function getNextDate()
    {
        $this->calcNextDate();

        return $this->nextDate;
    }

    /**
     * @return string
     */
    public function getNextDateStr()
    {
        $this->calcNextDate();

        return $this->nextDate
            ? $this->nextDate->format($this->externalDateFormat)
            : false;
    }

    /**
     * @return int
     */
    public function getNextDateCount()
    {
        $this->calcNextDate();

        return $this->nextDate ? $this->dateCount($this->nextDate) : 0;
    }

    /**
     * @return \Zend\Paginator\Paginator|false
     */
    public function getPaginator()
    {
        if (!$this->currentDate) {
            return false;
        }

        if ($this->paginator === null) {

            $select = $this->getCurrentDateSelect();

            $this->paginator = new \Zend\Paginator\Paginator(
                new Zend1DbTableSelect($select)
            );
        }

        return $this->paginator;
    }

    /**
     * @param DateTime $date
     * @return int
     */
    private function dateCount(DateTime $date)
    {
        $column = $this->quotedOrderColumn();

        $select = $this->selectClone()
            ->where($column . ' >= ?', $this->startOfDayDbValue($date))
            ->where($column . ' <= ?', $this->endOfDayDbValue($date));

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        return $paginator->getTotalItemCount();
    }

    /**
     * @return Application_Service_DayPictures
     */
    private function reset()
    {
        $this->nextDate = null;
        $this->prevDate = null;
        $this->paginator = null;

        return $this;
    }

    /**
     * @param DateTime $date
     * @return DateTime
     */
    private function endOfDay(DateTime $date)
    {
        $d = clone $date;
        return $d->setTime(23, 59, 59);
    }

    /**
     * @param DateTime $date
     * @return DateTime
     */
    private function startOfDay(DateTime $date)
    {
        $d = clone $date;
        return $d->setTime(0, 0, 0);
    }

    /**
     * @param DateTime $date
     * @return string
     */
    private function startOfDayDbValue(DateTime $date)
    {
        $d = $this->startOfDay($date)->setTimezone($this->dbTimezone);
        return $d->format($this->dbDateTimeFormat);
    }

    /**
     * @param DateTime $date
     * @return string
     */
    private function endOfDayDbValue(DateTime $date)
    {
        $d = $this->endOfDay($date)->setTimezone($this->dbTimezone);
        return $d->format($this->dbDateTimeFormat);
    }

    /**
     * @return Zend_Db_Table_Select
     */
    private function selectClone()
    {
        return clone $this->select;
    }

    /**
     * @return Zend_Db_Table_Select
     */
    public function getCurrentDateSelect()
    {
        $column = $this->quotedOrderColumn();

        $select = $this->selectClone()
            ->where($column . ' >= ?', $this->startOfDayDbValue($this->currentDate))
            ->where($column . ' <= ?', $this->endOfDayDbValue($this->currentDate))
            ->order($this->orderColumn . ' DESC');

        if ($this->_minDate) {
            $select->where($column . ' >= ?', $this->startOfDayDbValue($this->_minDate));
        }

        return $select;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function quotedOrderColumn()
    {
        if (!$this->orderColumn) {
            throw new Exception('Order column not configured');
        }

        $db = $this->select->getAdapter();

        $tableName = $this->select->getTable()->info('name');

        return $db->quoteIdentifier($tableName . '.' . $this->orderColumn);
    }
}