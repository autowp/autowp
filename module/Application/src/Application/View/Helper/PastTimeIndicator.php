<?php

namespace Application\View\Helper;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Laminas\View\Helper\AbstractHtmlElement;

class PastTimeIndicator extends AbstractHtmlElement
{
    /** @var DateTime */
    private $pastLimit;

    public function __construct()
    {
        $date = new DateTime('now');
        $date->sub(new DateInterval('P1D'));
        $this->pastLimit = $date;
    }

    /**
     * @param DateTime|string $time
     * @throws Exception
     */
    public function __invoke($time): string
    {
        if (! $time instanceof DateTime) {
            $dt = new DateTime();
            $dt->setTimestamp($time->getTimestamp());
            $tz = new DateTimeZone($time->getTimezone());
            $dt->setTimezone($tz);
            $time = $dt;
        }

        $icon = $time > $this->pastLimit ? 'fa-clock-o' : 'fa-calendar';

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return '<i class="fa ' . $icon . '" aria-hidden="true"></i> '
               . $this->view->escapeHtml($this->view->user()->humanTime($time));
    }
}
