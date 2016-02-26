<?php

class Car_Parent extends Project_Db_Table
{
    protected $_name = 'car_parent';
    protected $_primary = ['car_id', 'parent_id'];
    
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

    const
        TYPE_DEFAULT = 0,
        TYPE_TUNING = 1,
        TYPE_SPORT = 2,
        TYPE_DESIGN = 3;

    /**
     * @var Brand_Car
     */
    private $brandCarTable;

    /**
     * @var Brands
     */
    private $brandTable;

    /**
     * @return Brand_Car
     */
    private function getBrandCarTable()
    {
        return $this->brandCarTable
            ? $this->brandCarTable
            : $this->brandCarTable = new Brand_Car();
    }

    /**
     * @return Brands
     */
    private function getBrandTable()
    {
        return $this->brandTable
            ? $this->brandTable
            : $this->brandTable = new Brands();
    }

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
        if (!$parent->is_group) {
            throw new Exception("Only groups can have childs");
        }

        $id = (int)$car->id;
        $parentId = (int)$parent->id;

        $defaults = array(
            'type'    => self::TYPE_DEFAULT,
            'catname' => $id,
            'name'    => null
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
                'name'      => $options['name'],
                'timestamp' => new Zend_Db_Expr('now()'),
                'type'      => $options['type']
            ));
            $row->save();
        }

        $cpcTable = new Car_Parent_Cache();
        $cpcTable->rebuildCache($car);
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
    }

    public function getPathsToBrand($carId, Brands_Row $brand, array $options = array())
    {
        $carId = (int)$carId;
        if (!$carId) {
            throw new Exception("carId not provided");
        }

        $breakOnFirst = isset($options['breakOnFirst']) && $options['breakOnFirst'];

        $result = array();

        $limit = $breakOnFirst ? 1 : null;
        $brandCarRows = $this->getBrandCarTable()->fetchAll(array(
            'car_id = ?'   => $carId,
            'brand_id = ?' => $brand->id
        ), null, $limit);
        foreach ($brandCarRows as $brandCarRow) {
            $result[] = array(
                'car_catname' => $brandCarRow->catname,
                'path'        => array()
            );
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->fetchAll(array(
            'car_id = ?' => $carId
        ));

        foreach ($parents as $parent) {
            $paths = $this->getPathsToBrand($parent->parent_id, $brand, $options);

            foreach ($paths as $path) {
                $result[] = array(
                    'car_catname' => $path['car_catname'],
                    'path'        => array_merge($path['path'], array($parent->catname))
                );
            }

            if ($breakOnFirst && count($result)) {
                return $result;
            }
        }

        return $result;
    }

    public function getPaths($carId, array $options = array())
    {
        $carId = (int)$carId;
        if (!$carId) {
            throw new Exception("carId not provided");
        }

        $breakOnFirst = isset($options['breakOnFirst']) && $options['breakOnFirst'];

        $result = array();

        $db = $this->getBrandCarTable()->getAdapter();

        $select = $db->select()
            ->from('brands_cars', 'catname')
            ->join('brands', 'brands_cars.brand_id = brands.id', 'folder')
            ->where('brands_cars.car_id = ?', $carId);

        if ($breakOnFirst) {
            $select->limit(1);
        }

        $brandCarRows = $db->fetchAll($select);
        foreach ($brandCarRows as $brandCarRow) {
            $result[] = array(
                'brand_catname' => $brandCarRow['folder'],
                'car_catname'   => $brandCarRow['catname'],
                'path'          => array()
            );
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->fetchAll(array(
            'car_id = ?' => $carId
        ));

        foreach ($parents as $parent) {
            $paths = $this->getPaths($parent->parent_id, $options);

            foreach ($paths as $path) {
                $result[] = array(
                    'brand_catname' => $path['brand_catname'],
                    'car_catname'   => $path['car_catname'],
                    'path'          => array_merge($path['path'], array($parent->catname))
                );
            }

            if ($breakOnFirst && count($result)) {
                return $result;
            }
        }

        return $result;
    }
}