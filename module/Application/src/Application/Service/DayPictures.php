<?php

namespace Application\Service;

use DateTime;
use DateTimeZone;
use Exception;

use Zend\Db\Sql;
use Zend\Paginator;

use Autowp\Commons\Db\Table\Row;

use Application\Model\Picture;

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
     * @var Sql\Select
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
    private $minDate = null;

    /**
     * @var Paginator\Paginator
     */
    private $paginator;

    /**
     * @var Picture
     */
    private $picture;

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
     * @return DayPictures
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

    public function setPicture(Picture $picture)
    {
        $this->picture = $picture;

        return $this;
    }

    /**
     * @param string $timezone
     * @return DayPictures
     */
    public function setTimeZone($timezone)
    {
        $this->timezone = new DateTimeZone($timezone);

        return $this->reset();
    }

    /**
     * @param string $timezone
     * @return DayPictures
     */
    public function setDbTimeZone($timezone)
    {
        $this->dbTimezone = new DateTimeZone($timezone);

        return $this->reset();
    }

    /**
     * @param Sql\Select $select
     * @return DayPictures
     */
    public function setSelect(Sql\Select $select)
    {
        $this->select = $select;

        return $this->reset();
    }

    /**
     * @param DateTime $date
     * @return DayPictures
     */
    public function setMinDate(DateTime $date)
    {
        $this->minDate = $date;

        return $this;
    }

    /**
     * @param string $column
     * @return DayPictures
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
     * @return DateTime
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
     * @return DayPictures
     */
    public function setCurrentDate($date)
    {
        $dateObj = null;

        if (! empty($date)) {
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
        if (! $this->currentDate) {
            return false;
        }

        $paginator = $this->getPaginator();
        $count = $paginator ? $paginator->getTotalItemCount() : 0;

        return $count > 0;
    }

    /**
     * @suppress PhanUndeclaredMethod
     *
     * @return string|null
     */
    public function getLastDateStr()
    {
        $select = $this->selectClone()
            ->order($this->orderColumn . ' desc')
            ->limit(1);

        $lastPicture = $this->picture->getTable()->selectWith($select)->current();
        if (! $lastPicture) {
            return null;
        }

        $lastDate = Row::getDateTimeByColumnType('timestamp', $lastPicture[$this->orderColumn]);
        if (! $lastDate) {
            return null;
        }

        return $lastDate
            ->setTimeZone($this->timezone)
            ->format($this->externalDateFormat);
    }

    /**
     * @suppress PhanUndeclaredMethod
     *
     * @return DayPictures
     */
    private function calcPrevDate()
    {
        if (! $this->currentDate) {
            return $this;
        }

        if ($this->prevDate === null) {
            $select = $this->selectClone()
                ->where([
                    new Sql\Predicate\Operator(
                        $this->orderColumn,
                        Sql\Predicate\Operator::OP_LT,
                        $this->startOfDayDbValue($this->currentDate)
                    )
                ])
                ->order($this->orderColumn . ' DESC')
                ->limit(1);

            if ($this->minDate) {
                $select->where([
                    new Sql\Predicate\Operator(
                        $this->orderColumn,
                        Sql\Predicate\Operator::OP_GTE,
                        $this->startOfDayDbValue($this->minDate)
                    )
                ]);
            }

            $prevDatePicture = $this->picture->getTable()->selectWith($select)->current();

            $prevDate = false;
            if ($prevDatePicture) {
                $date = Row::getDateTimeByColumnType('timestamp', $prevDatePicture[$this->orderColumn]);
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
     * @suppress PhanUndeclaredMethod
     *
     * @return DayPictures
     */
    private function calcNextDate()
    {
        if (! $this->currentDate) {
            return $this;
        }

        if ($this->nextDate === null) {
            $select = $this->selectClone()
                ->where([
                    new Sql\Predicate\Operator(
                        $this->orderColumn,
                        Sql\Predicate\Operator::OP_GT,
                        $this->endOfDayDbValue($this->currentDate)
                    )
                ])
                ->order($this->orderColumn)
                ->limit(1);

            $nextDatePicture = $this->picture->getTable()->selectWith($select)->current();

            $nextDate = false;
            if ($nextDatePicture) {
                $date = Row::getDateTimeByColumnType('timestamp', $nextDatePicture[$this->orderColumn]);
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
     * @return Paginator\Paginator|false
     */
    public function getPaginator()
    {
        if (! $this->currentDate) {
            return false;
        }

        if ($this->paginator === null) {
            $select = $this->getCurrentDateSelect();

            $this->paginator = new Paginator\Paginator(
                new Paginator\Adapter\DbSelect($select, $this->picture->getTable()->getAdapter())
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
        $select = $this->selectClone()->where([
            new Sql\Predicate\Between(
                $this->orderColumn,
                $this->startOfDayDbValue($date),
                $this->endOfDayDbValue($date)
            ),
        ]);

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->picture->getTable()->getAdapter())
        );

        return $paginator->getTotalItemCount();
    }

    /**
     * @return DayPictures
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
     * @return Sql\Select
     */
    private function selectClone()
    {
        return clone $this->select;
    }

    /**
     * @return Sql\Select
     */
    public function getCurrentDateSelect()
    {
        $select = $this->selectClone()
            ->where([
                new Sql\Predicate\Between(
                    $this->orderColumn,
                    $this->startOfDayDbValue($this->currentDate),
                    $this->endOfDayDbValue($this->currentDate)
                )
            ])
            ->order($this->orderColumn . ' DESC');

        if ($this->minDate) {
            $select->where([
                new Sql\Predicate\Operator(
                    $this->orderColumn,
                    Sql\Predicate\Operator::OP_GTE,
                    $this->startOfDayDbValue($this->minDate)
                )
            ]);
        }

        return $select;
    }
}
