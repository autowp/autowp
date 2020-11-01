<?php

namespace Application\Service;

use Application\Model\Picture;
use Application\Module;
use Autowp\Commons\Db\Table\Row;
use DateTime;
use DateTimeZone;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Paginator;

use function Autowp\Commons\currentFromResultSetInterface;
use function is_string;
use function method_exists;
use function ucfirst;

class DayPictures
{
    private const DEFAULT_TIMEZONE = 'UTC';

    private DateTimeZone $timezone;

    private DateTimeZone $dbTimezone;

    private Sql\Select $select;

    private string $orderColumn;

    private string $externalDateFormat = 'Y-m-d';

    private string $dbDateTimeFormat = Module::MYSQL_DATETIME_FORMAT;

    private ?DateTime $currentDate;

    private ?DateTime $prevDate;

    private ?DateTime $nextDate;

    private ?DateTime $minDate;

    private ?Paginator\Paginator $paginator;

    private Picture $picture;

    public function __construct(array $options = [])
    {
        $this->currentDate = null;
        $this->prevDate    = null;
        $this->nextDate    = null;
        $this->minDate     = null;
        $this->timezone    = new DateTimeZone(self::DEFAULT_TIMEZONE);
        $this->dbTimezone  = new DateTimeZone(self::DEFAULT_TIMEZONE);

        $this->setOptions($options);
    }

    public function setOptions(array $options): self
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                throw new Exception("Unexpected option '$key'");
            }
        }

        return $this;
    }

    public function setPicture(Picture $picture): self
    {
        $this->picture = $picture;

        return $this;
    }

    public function setTimeZone(string $timezone): self
    {
        $this->timezone = new DateTimeZone($timezone);

        return $this->reset();
    }

    public function setDbTimeZone(string $timezone): self
    {
        $this->dbTimezone = new DateTimeZone($timezone);

        return $this->reset();
    }

    public function setSelect(Sql\Select $select): self
    {
        $this->select = $select;

        return $this->reset();
    }

    public function setMinDate(DateTime $date): self
    {
        $this->minDate = $date;

        return $this;
    }

    public function setOrderColumn(string $column): self
    {
        $this->orderColumn = $column;

        return $this->reset();
    }

    public function haveCurrentDate(): bool
    {
        return (bool) $this->currentDate;
    }

    public function getCurrentDate(): ?DateTime
    {
        return $this->currentDate;
    }

    public function getCurrentDateStr(): ?string
    {
        return $this->currentDate
            ? $this->currentDate->format($this->externalDateFormat)
            : null;
    }

    public function getCurrentDateCount(): int
    {
        return $this->currentDate ? $this->dateCount($this->currentDate) : 0;
    }

    /**
     * @param string|DateTime $date
     * @throws Exception
     */
    public function setCurrentDate($date): self
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

    public function haveCurrentDayPictures(): bool
    {
        if (! $this->currentDate) {
            return false;
        }

        $paginator = $this->getPaginator();
        $count     = $paginator ? $paginator->getTotalItemCount() : 0;

        return $count > 0;
    }

    /**
     * @throws Exception
     */
    public function getLastDateStr(): ?string
    {
        $select = $this->selectClone()
            ->order($this->orderColumn . ' desc')
            ->limit(1);

        $lastPicture = currentFromResultSetInterface($this->picture->getTable()->selectWith($select));
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
     * @throws Exception
     */
    private function calcPrevDate(): self
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
                    ),
                ])
                ->order($this->orderColumn . ' DESC')
                ->limit(1);

            if ($this->minDate) {
                $select->where([
                    new Sql\Predicate\Operator(
                        $this->orderColumn,
                        Sql\Predicate\Operator::OP_GTE,
                        $this->startOfDayDbValue($this->minDate)
                    ),
                ]);
            }

            $prevDatePicture = currentFromResultSetInterface($this->picture->getTable()->selectWith($select));

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
                $this->prevDate = null;
            }
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function getPrevDate(): ?DateTime
    {
        $this->calcPrevDate();

        return $this->prevDate;
    }

    /**
     * @throws Exception
     */
    public function getPrevDateStr(): ?string
    {
        $this->calcPrevDate();

        return $this->prevDate
            ? $this->prevDate->format($this->externalDateFormat)
            : null;
    }

    /**
     * @throws Exception
     */
    public function getPrevDateCount(): int
    {
        $this->calcPrevDate();

        return $this->prevDate ? $this->dateCount($this->prevDate) : 0;
    }

    /**
     * @throws Exception
     */
    private function calcNextDate(): self
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
                    ),
                ])
                ->order($this->orderColumn)
                ->limit(1);

            $nextDatePicture = currentFromResultSetInterface($this->picture->getTable()->selectWith($select));

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
                $this->nextDate = null;
            }
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function getNextDate(): ?DateTime
    {
        $this->calcNextDate();

        return $this->nextDate;
    }

    /**
     * @throws Exception
     */
    public function getNextDateStr(): ?string
    {
        $this->calcNextDate();

        return $this->nextDate
            ? $this->nextDate->format($this->externalDateFormat)
            : null;
    }

    /**
     * @throws Exception
     */
    public function getNextDateCount(): int
    {
        $this->calcNextDate();

        return $this->nextDate ? $this->dateCount($this->nextDate) : 0;
    }

    public function getPaginator(): ?Paginator\Paginator
    {
        if (! $this->currentDate) {
            return null;
        }

        if (! isset($this->paginator)) {
            $select = $this->getCurrentDateSelect();

            /** @var Adapter $adapter */
            $adapter         = $this->picture->getTable()->getAdapter();
            $this->paginator = new Paginator\Paginator(
                new Paginator\Adapter\DbSelect($select, $adapter)
            );
        }

        return $this->paginator;
    }

    private function dateCount(DateTime $date): int
    {
        $select = $this->selectClone()->where([
            new Sql\Predicate\Between(
                $this->orderColumn,
                $this->startOfDayDbValue($date),
                $this->endOfDayDbValue($date)
            ),
        ]);

        /** @var Adapter $adapter */
        $adapter   = $this->picture->getTable()->getAdapter();
        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $adapter)
        );

        return $paginator->getTotalItemCount();
    }

    private function reset(): self
    {
        $this->nextDate  = null;
        $this->prevDate  = null;
        $this->paginator = null;

        return $this;
    }

    private function endOfDay(DateTime $date): DateTime
    {
        $d = clone $date;
        return $d->setTime(23, 59, 59);
    }

    private function startOfDay(DateTime $date): DateTime
    {
        $d = clone $date;
        return $d->setTime(0, 0, 0);
    }

    private function startOfDayDbValue(DateTime $date): string
    {
        $d = $this->startOfDay($date)->setTimezone($this->dbTimezone);
        return $d->format($this->dbDateTimeFormat);
    }

    private function endOfDayDbValue(DateTime $date): string
    {
        $d = $this->endOfDay($date)->setTimezone($this->dbTimezone);
        return $d->format($this->dbDateTimeFormat);
    }

    private function selectClone(): Sql\Select
    {
        return clone $this->select;
    }

    public function getCurrentDateSelect(): Sql\Select
    {
        $select = $this->selectClone()
            ->where([
                new Sql\Predicate\Between(
                    $this->orderColumn,
                    $this->startOfDayDbValue($this->currentDate),
                    $this->endOfDayDbValue($this->currentDate)
                ),
            ])
            ->order($this->orderColumn . ' DESC');

        if ($this->minDate) {
            $select->where([
                new Sql\Predicate\Operator(
                    $this->orderColumn,
                    Sql\Predicate\Operator::OP_GTE,
                    $this->startOfDayDbValue($this->minDate)
                ),
            ]);
        }

        return $select;
    }
}
