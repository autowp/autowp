<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Zend_Date;

use Log_Events;
use Users;

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
        $userTable = new Users();
        $logTable = new Log_Events();
        $logAdapter = $logTable->getAdapter();

        $now = Zend_Date::now();
        $from = Zend_Date::now()->subDay(1);

        $rows = $logAdapter->fetchAll(
            $logAdapter->select()
                ->from($logTable->info('name'), [
                    'user_id',
                    'date' => 'date(add_datetime)',
                    'hour' => 'hour(add_datetime)',
                    'value' => 'count(1)'
                ])
                ->where('add_datetime >= ?', $from->get(MYSQL_DATETIME))
                ->where('add_datetime <= ?', $now->get(MYSQL_DATETIME))
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

        foreach ($data as $uid => $dates) {

            $line = [];

            $cDate = clone $from;
            while ($now->isLater($cDate)) {
                $dateStr = $cDate->get(MYSQL_DATE) . ' ' . $cDate->get(Zend_Date::HOUR_SHORT);

                $line[$dateStr] = isset($dates[$dateStr]) ? $dates[$dateStr] : 0;

                $cDate->addHour(1);
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