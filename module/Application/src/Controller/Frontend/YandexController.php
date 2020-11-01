<?php

namespace Application\Controller\Frontend;

use Application\Model\CarOfDay;
use DateTime;
use DateTimeZone;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

use function implode;
use function preg_match;
use function sha1;

/**
 * @method ViewModel forbiddenAction()
 */
class YandexController extends AbstractActionController
{
    private const DATE_FORMAT = 'Y-m-d';

    private string $secret;

    private int $price;

    private CarOfDay $itemOfDay;

    public function __construct(array $config, CarOfDay $itemOfDay)
    {
        $this->secret    = $config['secret'];
        $this->price     = $config['price'];
        $this->itemOfDay = $itemOfDay;
    }

    private function isAvailableDate(DateTime $date): ?DateTime
    {
        $result = null;

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

    /**
     * @return Response|ViewModel
     */
    public function informingAction()
    {
        /** @var Request $request */
        $request = $this->getRequest();

        if (! $request->isPost()) {
            return $this->forbiddenAction();
        }

        $fields = [
            'notification_type',
            'operation_id',
            'amount',
            'currency',
            'datetime',
            'sender',
            'codepro',
            'notification_secret',
            'label',
        ];

        $str = [];
        foreach ($fields as $field) {
            if ($field === 'notification_secret') {
                $str[] = $this->secret;
            } else {
                $str[] = (string) $this->params()->fromPost($field);
            }
        }
        $str = implode('&', $str);

        $sha1Hash = (string) $this->params()->fromPost('sha1_hash');

        if (sha1($str) !== $sha1Hash) {
            return $this->forbiddenAction();
        }

        $currency = $this->params()->fromPost('currency');
        if ($currency !== 643) {
            return $this->forbiddenAction();
        }

        $withdrawAmount = (float) $this->params()->fromPost('withdraw_amount');
        if ($withdrawAmount < $this->price) {
            return $this->forbiddenAction();
        }

        $label = (string) $this->params()->fromPost('label');
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

        $itemId = (int) $matches[2];
        $userId = (int) $matches[3];

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

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }
}
