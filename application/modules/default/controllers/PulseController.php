<?php
class PulseController extends Zend_Controller_Action
{
    protected $_lastColor = 0;
    protected $_colors = array(
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
    );

    protected function randomColor()
    {
        return $this->_colors[$this->_lastColor++ % count($this->_colors)];
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
                ->from($logTable->info('name'), array(
                    'user_id',
                    'date' => 'date(add_datetime)',
                    'hour' => 'hour(add_datetime)',
                    'value' => 'count(1)'
                ))
                ->where('add_datetime >= ?', $from->get(MYSQL_DATETIME))
                ->where('add_datetime <= ?', $now->get(MYSQL_DATETIME))
                ->group(array('user_id', 'date', 'hour'))
        );

        $data = array();
        foreach ($rows as $row) {
            $uid = $row['user_id'];
            $date = $row['date'] . ' ' . $row['hour'];
            $data[$uid][$date] = (int)$row['value'];
        }

        $grid = array();
        $legend = array();

        foreach ($data as $uid => $dates) {

            $line = array();

            $cDate = clone $from;
            while ($now->isLater($cDate)) {
                $dateStr = $cDate->get(MYSQL_DATE) . ' ' . $cDate->get(Zend_Date::HOUR_SHORT);

                $line[$dateStr] = isset($dates[$dateStr]) ? $dates[$dateStr] : 0;

                $cDate->addHour(1);
            }

            $color = $this->randomColor();

            $grid[$uid] = array(
                'line'  => $line,
                'color' => $color
            );

            $legend[$uid] = array(
                'user'  => $userTable->find($uid)->current(),
                'color' => $color
            );
        }

        $this->view->assign(array(
            'grid'   => $grid,
            'legend' => $legend
        ));
    }
}