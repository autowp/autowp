<?php

class Project_View_Helper_PastTimeIndicator extends Zend_View_Helper_Abstract
{
    /**
     * @var Zend_Date
     */
    private $_pastLimit;

    public function __construct()
    {
        $date = new DateTime('now');
        $date->sub(new DateInterval('P1D'));
        $this->_pastLimit = $date;
    }

    /**
     * @param Zend_Date|DateTime $date
     * @return string
     */
    public function pastTimeIndicator($time)
    {
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

        $icon = $time > $this->_pastLimit ? 'fa-clock-o' : 'fa-calendar';

        return '<i class="fa ' . $icon . '"></i> ' . $this->view->escape($this->view->user()->humanTime($time));
    }
}