<?php

namespace Application\Model\DbTable\Vehicle;

use Application\Db\Table;
use Application\Model\DbTable\BrandItem;
use Application\Model\DbTable\Vehicle\ParentCache;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;

use Exception;

use Zend_Db_Expr;

class ParentTable extends Table
{
    protected $_name = 'car_parent';
    protected $_primary = ['car_id', 'parent_id'];

    protected $_referenceMap = [
        'Car' => [
            'columns'       => ['car_id'],
            'refTableClass' => 'Car',
            'refColumns'    => ['id']
        ],
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => 'Car',
            'refColumns'    => ['id']
        ],
    ];

    const
        TYPE_DEFAULT = 0,
        TYPE_TUNING = 1,
        TYPE_SPORT = 2,
        TYPE_DESIGN = 3;

    /**
     * @var BrandItem
     */
    private $brandItemTable;

    /**
     * @return BrandItem
     */
    private function getBrandItemTable()
    {
        return $this->brandItemTable
            ? $this->brandItemTable
            : $this->brandItemTable = new BrandItem();
    }

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

        $toCheck = [$id];
        $ids = [];

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

    public function addParent(VehicleRow $car, VehicleRow $parent, array $options = [])
    {
        if (! $parent->is_group) {
            throw new Exception("Only groups can have childs");
        }

        if ($car->item_type_id != $parent->item_type_id) {
            throw new Exception("Parent and child must be same type");
        }

        $id = (int)$car->id;
        $parentId = (int)$parent->id;

        $defaults = [
            'type'    => self::TYPE_DEFAULT,
            'catname' => $id,
            'name'    => null
        ];
        $options = array_merge($defaults, $options);

        $parentIds = $this->collectParentIds($parentId);
        if (in_array($id, $parentIds)) {
            throw new Exception('Cycle detected');
        }

        $row = $this->fetchRow([
            'car_id = ?'    => $id,
            'parent_id = ?' => $parentId
        ]);
        if (! $row) {
            $row = $this->createRow([
                'car_id'    => $id,
                'parent_id' => $parentId,
                'catname'   => $options['catname'],
                'name'      => $options['name'],
                'timestamp' => new Zend_Db_Expr('now()'),
                'type'      => $options['type']
            ]);
            $row->save();
        }

        $cpcTable = new ParentCache();
        $cpcTable->rebuildCache($car);
    }

    public function removeParent(VehicleRow $car, VehicleRow $parent)
    {
        $id = (int)$car->id;
        $parentId = (int)$parent->id;

        $row = $this->fetchRow([
            'car_id = ?'    => $id,
            'parent_id = ?' => $parentId
        ]);
        if ($row) {
            $row->delete();
        }

        $cpcTable = new ParentCache();
        $cpcTable->rebuildCache($car);
    }

    public function getPathsToBrand($carId, $brand, array $options = [])
    {
        $carId = (int)$carId;
        if (! $carId) {
            throw new Exception("carId not provided");
        }

        $brandId = $brand;
        if ($brandId instanceof \Application\Model\DbTable\BrandRow) {
            $brandId = $brandId->id;
        }


        $breakOnFirst = isset($options['breakOnFirst']) && $options['breakOnFirst'];

        $result = [];

        $limit = $breakOnFirst ? 1 : null;
        $brandItemRows = $this->getBrandItemTable()->fetchAll([
            'car_id = ?'   => $carId,
            'brand_id = ?' => $brandId
        ], null, $limit);
        foreach ($brandItemRows as $brandItemRow) {
            $result[] = [
                'car_catname' => $brandItemRow->catname,
                'path'        => []
            ];
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->fetchAll([
            'car_id = ?' => $carId
        ]);

        foreach ($parents as $parent) {
            $paths = $this->getPathsToBrand($parent->parent_id, $brandId, $options);

            foreach ($paths as $path) {
                $result[] = [
                    'car_catname' => $path['car_catname'],
                    'path'        => array_merge($path['path'], [$parent->catname])
                ];
            }

            if ($breakOnFirst && count($result)) {
                return $result;
            }
        }

        return $result;
    }

    public function getPaths($carId, array $options = [])
    {
        $carId = (int)$carId;
        if (! $carId) {
            throw new Exception("carId not provided");
        }

        $breakOnFirst = isset($options['breakOnFirst']) && $options['breakOnFirst'];

        $result = [];

        $db = $this->getBrandItemTable()->getAdapter();

        $select = $db->select()
            ->from('brand_item', 'catname')
            ->join('brands', 'brand_item.brand_id = brands.id', 'folder')
            ->where('brand_item.car_id = ?', $carId);

        if ($breakOnFirst) {
            $select->limit(1);
        }

        $brandItemRows = $db->fetchAll($select);
        foreach ($brandItemRows as $brandItemRow) {
            $result[] = [
                'brand_catname' => $brandItemRow['folder'],
                'car_catname'   => $brandItemRow['catname'],
                'path'          => []
            ];
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->fetchAll([
            'car_id = ?' => $carId
        ]);

        foreach ($parents as $parent) {
            $paths = $this->getPaths($parent->parent_id, $options);

            foreach ($paths as $path) {
                $result[] = [
                    'brand_catname' => $path['brand_catname'],
                    'car_catname'   => $path['car_catname'],
                    'path'          => array_merge($path['path'], [$parent->catname])
                ];
            }

            if ($breakOnFirst && count($result)) {
                return $result;
            }
        }

        return $result;
    }
}
