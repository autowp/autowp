<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\Log\Event as LogEvent;
use Application\Model\DbTable\User;

use DateInterval;
use DateTime;

class PulseController extends AbstractActionController
{
    private $lastColor = 0;

    private $colors = [
        '#FF0000',
        '#00FF00',
        '#0000FF',
        '#FFFF00',
        '#FF00FF',
        '#00FFFF',
        '#880000',
        '#008800',
        '#000088',
        '#888800',
        '#880088',
        '#008888',
    ];

    private function randomColor()
    {
        return $this->colors[$this->lastColor++ % count($this->colors)];
    }

    public function indexAction()
    {
        $userTable = new User();
        $logTable = new LogEvent();
        $logAdapter = $logTable->getAdapter();

        $now = new DateTime();
        $from = (new DateTime())->sub(new DateInterval('P1D'));

        $rows = $logAdapter->fetchAll(
            $logAdapter->select()
                ->from($logTable->info('name'), [
                    'user_id',
                    'date' => 'date(add_datetime)',
                    'hour' => 'hour(add_datetime)',
                    'value' => 'count(1)'
                ])
                ->where('add_datetime >= ?', $from->format(MYSQL_DATETIME_FORMAT))
                ->where('add_datetime <= ?', $now->format(MYSQL_DATETIME_FORMAT))
                ->group(['user_id', 'date', 'hour'])
        );

        $data = [];
        foreach ($rows as $row) {
            $uid = $row['user_id'];
            $date = $row['date'] . ' ' . $row['hour'];
            $data[$uid][$date] = (int)$row['value'];
        }

        $grid = [];
        $legend = [];

        $hour = new DateInterval('PT1H');

        foreach ($data as $uid => $dates) {

            $line = [];

            $cDate = clone $from;
            while ($now > $cDate) {
                $dateStr = $cDate->format('Y-m-d G');

                $line[$dateStr] = isset($dates[$dateStr]) ? $dates[$dateStr] : 0;

                $cDate->add($hour);
            }

            $color = $this->randomColor();

            $grid[$uid] = [
                'line'  => $line,
                'color' => $color
            ];

            $legend[$uid] = [
                'user'  => $userTable->find($uid)->current(),
                'color' => $color
            ];
        }

        return [
            'grid'   => $grid,
            'legend' => $legend
        ];
    }
}