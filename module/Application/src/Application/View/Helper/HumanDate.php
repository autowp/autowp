<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use DateInterval;
use DateTime;
use DateTimeZone;
use IntlDateFormatter;

class HumanDate extends AbstractHelper
{
    /**
     * @var string
     */
    private $language;

    /**
     * @param string $language
     */
    public function __construct($language)
    {
        $this->language = $language;
    }

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
            $timezone = new DateTimeZone($time->getTimezone());
            $dateTime->setTimezone($timezone);
            $time = $dateTime;
        }

        $now = new DateTime('now');
        $now->setTimezone($time->getTimezone());
        $ymd = $time->format('Ymd');

        if ($ymd == $now->format('Ymd')) {
            return $this->view->translate('today');
        }

        $now->sub(new DateInterval('P1D'));
        if ($ymd == $now->format('Ymd')) {
            return $this->view->translate('yesterday');
        }

        $dateFormatter = new IntlDateFormatter($this->language, IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $dateFormatter->setTimezone($time->getTimezone());
        return $dateFormatter->format($time);
    }
}
