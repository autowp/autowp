<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use DateInterval;
use DateTime;
use DateTimeZone;
use IntlDateFormatter;

use Zend_Date;
use Zend_Registry;

class HumanDate extends AbstractHelper
{
    /**
     * Converts time to fuzzy time strings
     *
     * @param string|integer|Zend_Date|array $time
     */
    public function __invoke($time = null)
    {
        if ($time === null) {
            throw new \Zend\View\Exception\InvalidArgumentException('Expected parameter $time was not provided.');
        }

        if (!$time instanceof DateTime) {
            if (!$time instanceof Zend_Date) {
                $time = new Zend_Date($time);
            }
            $dt = new DateTime();
            $dt->setTimestamp($time->getTimestamp());
            $tz = new DateTimeZone($time->getTimezone());
            $dt->setTimezone($tz);
            $time = $dt;
        }

        $now = new DateTime('now');
        $now->setTimezone($time->getTimezone());
        $ymd = $time->format('Ymd');
        $isToday = $ymd == $now->format('Ymd');

        if ($isToday) {
            $s = $this->view->translate('today');
        } else {

            $now->sub(new DateInterval('P1D'));
            $isYesterday = $ymd == $now->format('Ymd');

            if ($isYesterday) {
                $s = $this->view->translate('yesterday');
            } else {
                $locale = Zend_Registry::get('Zend_Locale');
                $df = new IntlDateFormatter($locale->toString(), IntlDateFormatter::LONG, IntlDateFormatter::NONE);
                $df->setTimezone($time->getTimezone());
                $s = $df->format($time);
                //$s = $time->format(MYSQL_DATETIME_FORMAT);
                //$s = $time->get(Zend_Date::DATE_MEDIUM);
            }
        }

        return $s;
    }
}
