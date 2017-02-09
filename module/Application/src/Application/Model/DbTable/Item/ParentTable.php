<?php

namespace Application\Model\DbTable\Item;

use Autowp\Commons\Db\Table;
use Application\Model\DbTable;

class ParentTable extends Table
{
    protected $_name = 'item_parent';
    protected $_primary = ['item_id', 'parent_id'];

    protected $_referenceMap = [
        'Car' => [
            'columns'       => ['item_id'],
            'refTableClass' => DbTable\Item::class,
            'refColumns'    => ['id']
        ],
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => DbTable\Item::class,
            'refColumns'    => ['id']
        ],
    ];

    const
        TYPE_DEFAULT = 0,
        TYPE_TUNING = 1,
        TYPE_SPORT = 2,
        TYPE_DESIGN = 3;

    const MAX_CATNAME = 70;

    public function collectChildIds($id)
    {
        $cpTableName = $this->info('name');
        $adapter = $this->getAdapter();

        $toCheck = [$id];
        $ids = [];

        while (count($toCheck) > 0) {
            $ids = array_merge($ids, $toCheck);

            $toCheck = $adapter->fetchCol(
                $adapter->select()
                    ->from($cpTableName, 'item_id')
                    ->where('parent_id in (?)', $toCheck)
            );
        }

        return array_unique($ids);
    }

    public function collectParentIds($id)
    {
        $cpTableName = $this->info('name');
        $adapter = $this->getAdapter();

        $toCheck = [$id];
        $ids = [];

        while (count($toCheck) > 0) {
            $ids = array_merge($ids, $toCheck);

            $toCheck = $adapter->fetchCol(
                $adapter->select()
                    ->from($cpTableName, 'parent_id')
                    ->where('item_id in (?)', $toCheck)
            );
        }

        return array_unique($ids);
    }
}
