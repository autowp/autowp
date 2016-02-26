<?php

class Car_Parent_Cache extends Project_Db_Table
{
    protected $_name = 'car_parent_cache';
    protected $_primary = array('car_id', 'parent_id');

    protected $_referenceMap = array(
        'Car' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Car',
            'refColumns'    => array('id')
        ),
        'Parent' => array(
            'columns'       => array('parent_id'),
            'refTableClass' => 'Car',
            'refColumns'    => array('id')
        )
    );

    /**
     * @var Car_Parent
     */
    private $carParentTable;

    /**
     * @return Car_Parent
     */
    private function getCarParentTable()
    {
        return $this->carParentTable
            ? $this->carParentTable
            : $this->carParentTable = new Car_Parent();
    }

    private function collectParentInfo($id, $diff = 1)
    {
        $cpTable = $this->getCarParentTable();

        $cpTableName = $cpTable->info('name');
        $adapter = $cpTable->getAdapter();

        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($cpTableName, array('parent_id', 'type'))
                ->where('car_id = ?', $id)
        );

        $result = array();
        foreach ($rows as $row) {
            $parentId = $row['parent_id'];
            $isTuning = $row['type'] == Car_Parent::TYPE_TUNING;
            $isSport  = $row['type'] == Car_Parent::TYPE_SPORT;
            $isDesign = $row['type'] == Car_Parent::TYPE_DESIGN;
            $result[$parentId] = array(
                'diff'   => $diff,
                'tuning' => $isTuning,
                'sport'  => $isSport,
                'design' => $isDesign
            );

            foreach ($this->collectParentInfo($parentId, $diff+1) as $pid => $info) {
                if (!isset($result[$pid]) || $info['diff'] < $result[$pid]['diff']) {
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
        $cpTable = new Car_Parent();

        $cpTableName = $cpTable->info('name');
        $adapter = $cpTable->getAdapter();

        $toCheck = array($id);
        $ids = array();

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
                    ->where('car_id in (?)', $toCheck)
            );

            $diff++;
        }

        return $ids;
    }*/

    /*public function rebuildCache(Cars_Row $car)
    {
        $id = (int)$car->id;

        $parentIds = $this->_collectParentIds($id);

        foreach ($parentIds as $parentId => $diff) {
            $row = $this->fetchRow(array(
                'car_id = ?'    => $id,
                'parent_id = ?' => $parentId
            ));
            if (!$row) {
                $row = $this->createRow(array(
                    'car_id'    => $id,
                    'parent_id' => $parentId,
                    'diff'      => $diff
                ));
                $row->save();
            }
            if ($row->diff != $diff) {
                $row->diff = $diff;
                $row->save();
            }
        }

        $filter = array(
            'car_id = ?' => $id
        );
        if ($parentIds) {
            $filter['parent_id not in (?)'] = array_keys($parentIds);
        }

        $this->delete($filter);

        $carTable = new Cars();

        $childCars = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $id)
        );

        foreach ($childCars as $childCar) {
            $this->rebuildCache($childCar);
        }
    }*/

    public function rebuildCache(Cars_Row $car)
    {
        $id = (int)$car->id;

        $parentInfo = $this->collectParentInfo($id);
        $parentInfo[$id] = array(
            'diff'  => 0,
            'tuning' => false,
            'sport'  => false,
            'design' => false
        );

        $updates = 0;

        foreach ($parentInfo as $parentId => $info) {
            $row = $this->fetchRow(array(
                'car_id = ?'    => $id,
                'parent_id = ?' => $parentId
            ));
            if (!$row) {
                $row = $this->createRow(array(
                    'car_id'    => $id,
                    'parent_id' => $parentId,
                    'diff'      => $info['diff'],
                    'tuning'    => $info['tuning'] ? 1 : 0,
                    'sport'     => $info['sport']  ? 1 : 0,
                    'design'    => $info['design'] ? 1 : 0
                ));
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

        $filter = array(
            'car_id = ?' => $id
        );
        if ($parentInfo) {
            $filter['parent_id not in (?)'] = array_keys($parentInfo);
        }

        $this->delete($filter);

        $carTable = new Cars();

        $childCars = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $id)
        );

        foreach ($childCars as $childCar) {
            $this->rebuildCache($childCar);
        }

        return $updates;
    }
}