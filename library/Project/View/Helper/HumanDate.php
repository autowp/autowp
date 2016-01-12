<?php
class Project_View_Helper_HumanDate extends Zend_View_Helper_Abstract
{
    /**
     * Converts time to fuzzy time strings
     *
     * @param string|integer|Zend_Date|array $time
     */
    public function humanDate($time = null)
    {
        if ($time === null) {
            require_once 'Zend/View/Exception.php';
            throw new Zend_View_Exception('Expected parameter $time was not provided.');
        }

        if (!$time instanceof DateTime) {
            require_once 'Zend/Date.php';
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
