<?php

namespace Application\Controller\Api;

use IntlDateFormatter;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Application\Model\CarOfDay;

class DonateController extends AbstractActionController
{
    private $carOfDay;

    /**
     * @var array
     */
    private $yandexConfig;

    public function __construct(
        CarOfDay $carOfDay,
        array $yandexConfig
    ) {
        $this->carOfDay = $carOfDay;
        $this->yandexConfig = $yandexConfig;
    }

    public function getVodAction()
    {
        $dates = [];

        $dateFormatter = new IntlDateFormatter($this->language(), IntlDateFormatter::LONG, IntlDateFormatter::NONE);

        foreach ($this->carOfDay->getNextDates() as $nextDate) {
            $dates[] = [
                'name'  => $dateFormatter->format($nextDate['date']),
                'value' => $nextDate['date']->format('Y-m-d'),
                'free'  => $nextDate['free']
            ];
        }

        return new JsonModel([
            'dates' => $dates,
            'sum'   => $this->yandexConfig['price']
        ]);
    }
}
