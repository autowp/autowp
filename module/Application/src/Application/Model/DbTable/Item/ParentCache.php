<?php

namespace Application\Model\DbTable\Item;

use Autowp\Commons\Db\Table;
use Application\Model\DbTable;

class ParentCache extends Table
{
    protected $_name = 'item_parent_cache';
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
        ]
    ];

    /**
     * @var DbTable\Item\ParentTable
     */
    private $itemParentTable;

    /**
     * @return DbTable\Item\ParentTable
     */
    private function getCarParentTable()
    {
        return $this->itemParentTable
            ? $this->itemParentTable
            : $this->itemParentTable = new DbTable\Item\ParentTable();
    }

    private function collectParentInfo($id, $diff = 1)
    {
        $cpTable = $this->getCarParentTable();

        $cpTableName = $cpTable->info('name');
        $adapter = $cpTable->getAdapter();

        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($cpTableName, ['parent_id', 'type'])
                ->where('item_id = ?', $id)
        );

        $result = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'];
            $isTuning = $row['type'] == DbTable\Item\ParentTable::TYPE_TUNING;
            $isSport  = $row['type'] == DbTable\Item\ParentTable::TYPE_SPORT;
            $isDesign = $row['type'] == DbTable\Item\ParentTable::TYPE_DESIGN;
            $result[$parentId] = [
                'diff'   => $diff,
                'tuning' => $isTuning,
                'sport'  => $isSport,
                'design' => $isDesign
            ];

            foreach ($this->collectParentInfo($parentId, $diff + 1) as $pid => $info) {
                if (! isset($result[$pid]) || $info['diff'] < $result[$pid]['diff']) {
                    $result[$pid] = $info;
                    $result[$pid]['tuning'] = $result[$pid]['tuning'] || $isTuning;
                    $result[$pid]['sport']  = $result[$pid]['sport']  || $isSport;
                    $result[$pid]['design'] = $result[$pid]['design'] || $isDesign;
                }
            }
        }

        return $result;
    }

    /*protected function _collectParentIds($id)
    {
        $cpTable = new DbTable\Item\ParentTable();

        $cpTableName = $cpTable->info('name');
        $adapter = $cpTable->getAdapter();

        $toCheck = [$id];
        $ids = [];

        $diff = 0;

        while (count($toCheck) > 0) {
            foreach ($toCheck as $cid) {
                if (!isset($ids[$cid])) {
                    $ids[$cid] = $diff;
                }
            }

            $toCheck = $adapter->fetchCol(
                $adapter->select()
                    ->from($cpTableName, 'parent_id')
                    ->where('item_id in (?)', $toCheck)
            );

            $diff++;
        }

        return $ids;
    }*/

    /*public function rebuildCache(DbTable\Item\Row $car)
    {
        $id = (int)$car->id;

        $parentIds = $this->_collectParentIds($id);

        foreach ($parentIds as $parentId => $diff) {
            $row = $this->fetchRow([
                'item_id = ?'    => $id,
                'parent_id = ?' => $parentId
            ]);
            if (!$row) {
                $row = $this->createRow([
                    'item_id'    => $id,
                    'parent_id' => $parentId,
                    'diff'      => $diff
                ]);
                $row->save();
            }
            if ($row->diff != $diff) {
                $row->diff = $diff;
                $row->save();
            }
        }

        $filter = [
            'item_id = ?' => $id
        ];
        if ($parentIds) {
            $filter['parent_id not in (?)'] = array_keys($parentIds);
        }

        $this->delete($filter);

        $itemTable = new DbTable\Item();

        $childCars = $itemTable->fetchAll(
            $itemTable->select(true)
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $id)
        );

        foreach ($childCars as $childCar) {
            $this->rebuildCache($childCar);
        }
    }*/

    public function rebuildCache(DbTable\Item\Row $car)
    {
        $id = (int)$car->id;

        $parentInfo = $this->collectParentInfo($id);
        $parentInfo[$id] = [
            'diff'  => 0,
            'tuning' => false,
            'sport'  => false,
            'design' => false
        ];

        $updates = 0;

        foreach ($parentInfo as $parentId => $info) {
            $row = $this->fetchRow([
                'item_id = ?'   => $id,
                'parent_id = ?' => $parentId
            ]);
            if (! $row) {
                $row = $this->createRow([
                    'item_id'   => $id,
                    'parent_id' => $parentId,
                    'diff'      => $info['diff'],
                    'tuning'    => $info['tuning'] ? 1 : 0,
                    'sport'     => $info['sport'] ? 1 : 0,
                    'design'    => $info['design'] ? 1 : 0
                ]);
                $updates++;
                $row->save();
            }
            $changes = false;
            if ($row->diff != $info['diff']) {
                $row->diff = $info['diff'];
                $changes = true;
            }

            if ($row->tuning xor $info['tuning']) {
                $row->tuning = $info['tuning'] ? 1 : 0;
                $changes = true;
            }

            if ($row->sport xor $info['sport']) {
                $row->sport = $info['sport'] ? 1 : 0;
                $changes = true;
            }

            if ($row->design xor $info['design']) {
                $row->design = $info['design'] ? 1 : 0;
                $changes = true;
            }

            if ($changes) {
                $updates++;
                $row->save();
            }
        }

        $filter = [
            'item_id = ?' => $id
        ];
        if ($parentInfo) {
            $filter['parent_id not in (?)'] = array_keys($parentInfo);
        }

        $this->delete($filter);

        $itemTable = new DbTable\Item();

        $childCars = $itemTable->fetchAll(
            $itemTable->select(true)
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $id)
        );

        foreach ($childCars as $childCar) {
            $this->rebuildCache($childCar);
        }

        return $updates;
    }
}
