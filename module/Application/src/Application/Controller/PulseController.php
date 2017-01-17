<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable\Log\Event as LogEvent;

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
        
        switch ($this->params()->fromQuery('period')) {
            case 'year':
                $period = 'year';
                $from = (new DateTime())->sub(new DateInterval('P1Y'));
                $group = ['user_id', 'year', 'month'];
                $subPeriod = new DateInterval('P1M');
                $format = 'Y-n';
                $columns = [
                    'user_id',
                    'year' => 'year(add_datetime)',
                    'month' => 'month(add_datetime)',
                    'value' => 'count(1)'
                ];
                break;
            case 'month':
                $period = 'month';
                $from = (new DateTime())->sub(new DateInterval('P1M'));
                $group = ['user_id', 'date'];
                $subPeriod = new DateInterval('P1D');
                $format = 'Y-m-d';
                $columns = [
                    'user_id',
                    'date' => 'date(add_datetime)',
                    'value' => 'count(1)'
                ];
                break;
            default:
                $period = 'day';
                $from = (new DateTime())->sub(new DateInterval('P1D'));
                $group = ['user_id', 'date', 'hour'];
                $subPeriod = new DateInterval('PT1H');
                $format = 'Y-m-d G';
                $columns = [
                    'user_id',
                    'date'  => 'date(add_datetime)',
                    'hour'  => 'hour(add_datetime)',
                    'value' => 'count(1)'
                ];
                break;
        }

        $rows = $logAdapter->fetchAll(
            $logAdapter->select()
                ->from($logTable->info('name'), $columns)
                ->where('add_datetime >= ?', $from->format(MYSQL_DATETIME_FORMAT))
                ->where('add_datetime <= ?', $now->format(MYSQL_DATETIME_FORMAT))
                ->group($group)
        );

        $data = [];
        foreach ($rows as $row) {
            $uid = $row['user_id'];
            switch ($period) {
                case 'year':
                    $date = $row['year'] . '-' . $row['month'];
                    break;
                case 'month':
                    $date = $row['date'];
                    break;
                default:
                    $date = $row['date'] . ' ' . $row['hour'];
                    break;
            }
            
            $data[$uid][$date] = (int)$row['value'];
        }

        $grid = [];
        $legend = [];

        foreach ($data as $uid => $dates) {
            $line = [];

            $cDate = clone $from;
            while ($now > $cDate) {
                $dateStr = $cDate->format($format);

                $line[$dateStr] = isset($dates[$dateStr]) ? $dates[$dateStr] : 0;

                $cDate->add($subPeriod);
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
