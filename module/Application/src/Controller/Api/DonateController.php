<?php

namespace Application\Controller\Api;

use Application\Model\CarOfDay;
use IntlDateFormatter;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

class DonateController extends AbstractActionController
{
    private CarOfDay $carOfDay;

    private array $yandexConfig;

    public function __construct(
        CarOfDay $carOfDay,
        array $yandexConfig
    ) {
        $this->carOfDay     = $carOfDay;
        $this->yandexConfig = $yandexConfig;
    }

    public function getVodAction(): JsonModel
    {
        $dates = [];

        $dateFormatter = new IntlDateFormatter($this->language(), IntlDateFormatter::LONG, IntlDateFormatter::NONE);

        foreach ($this->carOfDay->getNextDates() as $nextDate) {
            $dates[] = [
                'name'  => $dateFormatter->format($nextDate['date']),
                'value' => $nextDate['date']->format('Y-m-d'),
                'free'  => $nextDate['free'],
            ];
        }

        return new JsonModel([
            'dates' => $dates,
            'sum'   => $this->yandexConfig['price'],
        ]);
    }
}
