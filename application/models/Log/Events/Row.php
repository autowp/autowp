<?php

use Application\Db\Table\Row;

class Log_Events_Row extends Row
{
    public function assign($items)
    {
        $items = is_array($items) ? $items : array($items);

        foreach ($items as $item) {
            if (!($item instanceof Zend_Db_Table_Row_Abstract)) {
                throw new Exception('Not a table row');
            }

            $table = $item->getTable();

            $col = $tableName = null;
            switch (true) {
                case $table instanceof Picture:
                    $col = 'picture_id';
                    $tableName = 'log_events_pictures';
                    break;
                case $table instanceof Cars:
                    $col = 'car_id';
                    $tableName = 'log_events_cars';
                    break;
                case $table instanceof Brands:
                    $col = 'brand_id';
                    $tableName = 'log_events_brands';
                    break;
                case $table instanceof Engines:
                    $col = 'engine_id';
                    $tableName = 'log_events_engines';
                    break;
                case $table instanceof Articles:
                    $col = 'article_id';
                    $tableName = 'log_events_articles';
                    break;
                case $table instanceof \Application\Model\DbTable\Twins\Group:
                    $col = 'twins_group_id';
                    $tableName = 'log_events_twins_groups';
                    break;
                case $table instanceof Users:
                    $col = 'user_id';
                    $tableName = 'log_events_user';
                    break;
                case $table instanceof Factory:
                    $col = 'factory_id';
                    $tableName = 'log_events_factory';
                    break;
                default:
                    throw new Exception('Unknown data type');
            }

            if ($col && $tableName) {
                $this->getTable()->getAdapter()->insert($tableName, array(
                    'log_event_id' => $this->id,
                    $col           => $item->id
                ));
            }
        }
        return $this;
    }
}