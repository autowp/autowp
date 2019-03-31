<?php

namespace Application\Controller\Frontend;

use DateTime;
use DateTimeZone;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Controller\Plugin\ForbiddenAction;
use Application\Model\CarOfDay;

/**
 * Class YandexController
 * @package Application\Controller\Frontend
 *
 * @method ForbiddenAction forbiddenAction()
 */
class YandexController extends AbstractActionController
{
    const DATE_FORMAT = 'Y-m-d';

    private $secret;

    private $price;

    /**
     * @var CarOfDay
     */
    private $itemOfDay;

    public function __construct(array $config, CarOfDay $itemOfDay)
    {
        $this->secret = $config['secret'];
        $this->price = $config['price'];
        $this->itemOfDay = $itemOfDay;
    }

    private function isAvailableDate(DateTime $date)
    {
        $result = false;

        $dateStr = $date->format(self::DATE_FORMAT);

        $nextDates = $this->itemOfDay->getNextDates();
        foreach ($nextDates as $nextDate) {
            if ($nextDate['date']->format(self::DATE_FORMAT) === $dateStr) {
                $result = $nextDate['free'];
                break;
            }
        }

        return $result;
    }

    public function informingAction()
    {
        $request = $this->getRequest();

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if (! $request->isPost()) {
            return $this->forbiddenAction();
        }

        $fields = ['notification_type', 'operation_id', 'amount', 'currency',
            'datetime', 'sender', 'codepro', 'notification_secret', 'label'];

        $str = [];
        foreach ($fields as $field) {
            if ($field == 'notification_secret') {
                $str[] = $this->secret;
            } else {
                $str[] = (string)$this->params()->fromPost($field);
            }
        }
        $str = implode('&', $str);

        $sha1Hash = (string)$this->params()->fromPost('sha1_hash');

        if (sha1($str) !== $sha1Hash) {
            return $this->forbiddenAction();
        }

        $currency = $this->params()->fromPost('currency');
        if ($currency != 643) {
            return $this->forbiddenAction();
        }

        $withdrawAmount = (float)$this->params()->fromPost('withdraw_amount');
        if ($withdrawAmount < $this->price) {
            return $this->forbiddenAction();
        }

        $label = (string)$this->params()->fromPost('label');
        if (! preg_match('|^vod/([0-9]{4}-[0-9]{2}-[0-9]{2})/([0-9]+)/([0-9]+)$|isu', $label, $matches)) {
            return $this->forbiddenAction();
        }

        $timezone = new DateTimeZone('UTC');
        $dateTime = DateTime::createFromFormat(self::DATE_FORMAT, $matches[1], $timezone);
        if (! $dateTime) {
            return $this->forbiddenAction();
        }

        if (! $this->isAvailableDate($dateTime)) {
            return $this->forbiddenAction();
        }

        $itemId = (int)$matches[2];
        $userId = (int)$matches[3];

        if (! $itemId) {
            return $this->forbiddenAction();
        }

        $unaccepted = $this->params()->fromPost('unaccepted') === 'true';
        if ($unaccepted) {
            return $this->forbiddenAction();
        }

        $success = $this->itemOfDay->setItemOfDay($dateTime, $itemId, $userId);
        if (! $success) {
            return $this->forbiddenAction();
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(200);
    }
}
