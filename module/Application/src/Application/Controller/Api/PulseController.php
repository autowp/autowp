<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\User\Model\User;
use DateInterval;
use DateTime;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;

use Laminas\View\Model\ViewModel;
use function count;

class PulseController extends AbstractActionController
{
    private TableGateway $logTable;

    private int $lastColor = 0;

    private array $colors = [
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

    private User $userModel;

    private AbstractRestHydrator $userHydrator;

    public function __construct(
        TableGateway $logTable,
        User $userModel,
        AbstractRestHydrator $userHydrator
    ) {
        $this->logTable     = $logTable;
        $this->userModel    = $userModel;
        $this->userHydrator = $userHydrator;
    }

    private function randomColor()
    {
        return $this->colors[$this->lastColor++ % count($this->colors)];
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        $now = new DateTime();

        switch ($this->params()->fromQuery('period')) {
            case 'year':
                $period    = 'year';
                $from      = (new DateTime())->sub(new DateInterval('P1Y'));
                $group     = ['user_id', 'year', 'month'];
                $subPeriod = new DateInterval('P1M');
                $format    = 'Y-n';
                $columns   = [
                    'user_id',
                    'year'  => new Sql\Expression('year(add_datetime)'),
                    'month' => new Sql\Expression('month(add_datetime)'),
                    'value' => new Sql\Expression('count(1)'),
                ];
                break;
            case 'month':
                $period    = 'month';
                $from      = (new DateTime())->sub(new DateInterval('P1M'));
                $group     = ['user_id', 'date'];
                $subPeriod = new DateInterval('P1D');
                $format    = 'Y-m-d';
                $columns   = [
                    'user_id',
                    'date'  => new Sql\Expression('date(add_datetime)'),
                    'value' => new Sql\Expression('count(1)'),
                ];
                break;
            default:
                $period    = 'day';
                $from      = (new DateTime())->sub(new DateInterval('P1D'));
                $group     = ['user_id', 'date', 'hour'];
                $subPeriod = new DateInterval('PT1H');
                $format    = 'Y-m-d G';
                $columns   = [
                    'user_id',
                    'date'  => new Sql\Expression('date(add_datetime)'),
                    'hour'  => new Sql\Expression('hour(add_datetime)'),
                    'value' => new Sql\Expression('count(1)'),
                ];
                break;
        }

        $select = new Sql\Select($this->logTable->getTable());
        $select
            ->columns($columns)
            ->where([
                new Sql\Predicate\Between(
                    'add_datetime',
                    $from->format(MYSQL_DATETIME_FORMAT),
                    $now->format(MYSQL_DATETIME_FORMAT)
                ),
            ])
            ->group($group);

        $rows = $this->logTable->selectWith($select);

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

            $data[$uid][$date] = (int) $row['value'];
        }

        $grid   = [];
        $legend = [];

        foreach ($data as $uid => $dates) {
            $line = [];

            $cDate = clone $from;
            while ($now > $cDate) {
                $dateStr = $cDate->format($format);

                $line[] = $dates[$dateStr] ?? 0;

                $cDate->add($subPeriod);
            }

            $color = $this->randomColor();

            $user = $this->userModel->getRow((int) $uid);

            $grid[] = [
                'line'  => $line,
                'color' => $color,
                'label' => $user ? $user['name'] : '',
            ];

            $legend[] = [
                'user'  => $user ? $this->userHydrator->extract($user) : null,
                'color' => $color,
            ];
        }

        $labels = [];
        $cDate  = clone $from;
        while ($now > $cDate) {
            $labels[] = $cDate->format($format);

            $cDate->add($subPeriod);
        }

        return new JsonModel([
            'grid'   => $grid,
            'legend' => $legend,
            'labels' => $labels,
        ]);
    }
}
