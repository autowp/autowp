<?php

namespace Application\Model\DbTable\Log;

use Application\Db\Table\Row;

use Zend_Db_Table_Row_Abstract;

use Exception;

class EventRow extends Row
{
    public function assign($items)
    {
        $items = is_array($items) ? $items : [$items];

        foreach ($items as $item) {
            if (! ($item instanceof Zend_Db_Table_Row_Abstract)) {
                throw new Exception('Not a table row');
            }

            $table = $item->getTable();

            $col = $tableName = null;
            switch (true) {
                case $table instanceof \Application\Model\DbTable\Picture:
                    $col = 'picture_id';
                    $tableName = 'log_events_pictures';
                    break;
                case $table instanceof \Application\Model\DbTable\Vehicle:
                    $col = 'item_id';
                    $tableName = 'log_events_cars';
                    break;
                case $table instanceof \Application\Model\DbTable\Brand:
                    $col = 'brand_id';
                    $tableName = 'log_events_brands';
                    break;
                case $table instanceof \Application\Model\DbTable\Article:
                    $col = 'article_id';
                    $tableName = 'log_events_articles';
                    break;
                case $table instanceof \Application\Model\DbTable\Twins\Group:
                    $col = 'twins_group_id';
                    $tableName = 'log_events_twins_groups';
                    break;
                case $table instanceof \Autowp\User\Model\DbTable\User:
                    $col = 'user_id';
                    $tableName = 'log_events_user';
                    break;
                case $table instanceof \Application\Model\DbTable\Factory:
                    $col = 'factory_id';
                    $tableName = 'log_events_factory';
                    break;
                default:
                    throw new Exception('Unknown data type');
            }

            if ($col && $tableName) {
                $this->getTable()->getAdapter()->insert($tableName, [
                    'log_event_id' => $this->id,
                    $col           => $item->id
                ]);
            }
        }
        return $this;
    }
}
