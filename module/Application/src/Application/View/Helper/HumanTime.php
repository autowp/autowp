<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use DateTime;

class HumanTime extends AbstractHelper
{
    /**
     * Converts time to fuzzy time strings
     *
     * @param string|integer|DateTime|array $time
     */
    public function __invoke($time = null)
    {
        if ($time === null) {
            throw new \Zend\View\Exception\InvalidArgumentException('Expected parameter $time was not provided.');
        }

        if (!$time instanceof DateTime) {
            $dateTime = new DateTime();
            $dateTime->setTimestamp($time->getTimestamp());
            $time = $dateTime;
        }

        $now = new DateTime('now');

        $diff = $now->getTimestamp() - $time->getTimestamp();

        if ($diff > 0 && $diff <= 50) {
            //less than 50 seconds
            return $this->view->translate('few seconds ago');
        }

        if ($diff > 50 && $diff < (60+30)) {
            //more than 50 seconds
            //less than minute and 30 seconds
            return $this->view->translate('a minute ago');
        }

        if ($diff >= (60+30) && $diff < (60*55)) {
            //more than minute and 30 seconds
            //less than 55 minutes
            $minutes = $diff / 60;
            $minutes = round($minutes, 0);
            return sprintf($this->view->translatePlural('%1$s minutes ago', null, $minutes), $minutes);
        }

        if ($diff >= (60*55) && $diff < (60*60+60*30)) {
            //more than 55 minutes
            //less than hour and 30 minutes
            return $this->view->translate('an hour ago');
        }

        if ($diff >= (60*60+60*30) && $diff < (60 * 60 * 23.5)) {
            //more than hour and 30 minutes
            //less than 23 and half hour
            $hours = $diff / (60*60);
            $hours = round($hours, 0);
            return sprintf($this->view->translatePlural('%1$s hours ago', null, $hours), $hours);
        }

        return $this->view->humanDate($time);
    }
}
