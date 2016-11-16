<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use DateInterval;
use DateTime;
use DateTimeZone;

class PastTimeIndicator extends AbstractHtmlElement
{
    /**
     * @var DateTime
     */
    private $pastLimit;

    public function __construct()
    {
        $date = new DateTime('now');
        $date->sub(new DateInterval('P1D'));
        $this->pastLimit = $date;
    }

    /**
     * @param DateTime $date
     * @return string
     */
    public function __invoke($time)
    {
        if (! $time instanceof DateTime) {
            $dt = new DateTime();
            $dt->setTimestamp($time->getTimestamp());
            $tz = new DateTimeZone($time->getTimezone());
            $dt->setTimezone($tz);
            $time = $dt;
        }

        $icon = $time > $this->pastLimit ? 'fa-clock-o' : 'fa-calendar';

        return '<i class="fa ' . $icon . '"></i> ' . $this->view->escapeHtml($this->view->user()->humanTime($time));
    }
}
