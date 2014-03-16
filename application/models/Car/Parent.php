<?php

class Car_Parent extends Project_Db_Table
{
    protected $_name = 'car_parent';
    protected $_primary = array('car_id', 'parent_id');

    const
        TYPE_DEFAULT = 0,
        TYPE_TUNING = 1,
        TYPE_SPORT = 2;

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
        ),
    );

    public function collectChildIds($id)
    {
        $cpTableName = $this->info('name');
        $adapter = $this->getAdapter();

        $toCheck = array($id);
        $ids = array();

        while (count($toCheck) > 0) {
            $ids = array_merge($ids, $toCheck);

            $toCheck = $adapter->fetchCol(
                $adapter->select()
                    ->from($cpTableName, 'car_id')
                    ->where('parent_id in (?)', $toCheck)
            );
        }

        return array_unique($ids);
    }

    public function collectParentIds($id)
    {
        $cpTableName = $this->info('name');
        $adapter = $this->getAdapter();

        $toCheck = array($id);
        $ids = array();

        while (count($toCheck) > 0) {
            $ids = array_merge($ids, $toCheck);

            $toCheck = $adapter->fetchCol(
                $adapter->select()
                    ->from($cpTableName, 'parent_id')
                    ->where('car_id in (?)', $toCheck)
            );
        }

        return array_unique($ids);
    }

    public function addParent(Cars_Row $car, Cars_Row $parent, array $options = array())
    {
        $id = (int)$car->id;
        $parentId = (int)$parent->id;

        $defaults = array(
            'type'    => self::TYPE_DEFAULT,
            'catname' => $id
        );
        $options = array_merge($defaults, $options);

        $parentIds = $this->collectParentIds($parentId);
        if (in_array($id, $parentIds)) {
            throw new Exception('Cycle detected');
        }

        $row = $this->fetchRow(array(
            'car_id = ?'    => $id,
            'parent_id = ?' => $parentId
        ));
        if (!$row) {
            $row = $this->createRow(array(
                'car_id'    => $id,
                'parent_id' => $parentId,
                'catname'   => $options['catname'],
                'timestamp' => new Zend_Db_Expr('now()'),
                'type'      => $options['type']
            ));
            $row->save();
        }

        $cpcTable = new Car_Parent_Cache();
        $cpcTable->rebuildCache($car);

        $modelCarTable = new Models_Cars();
        $modelCarTable->updateInheritaceRecursive($car);
    }

    public function removeParent(Cars_Row $car, Cars_Row $parent)
    {
        $id = (int)$car->id;
        $parentId = (int)$parent->id;

        $row = $this->fetchRow(array(
            'car_id = ?'    => $id,
            'parent_id = ?' => $parentId
        ));
        if ($row) {
            $row->delete();
        }

        $cpcTable = new Car_Parent_Cache();
        $cpcTable->rebuildCache($car);

        $modelCarTable = new Models_Cars();
        $modelCarTable->updateInheritaceRecursive($car);
    }
}