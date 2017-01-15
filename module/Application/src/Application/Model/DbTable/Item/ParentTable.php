<?php

namespace Application\Model\DbTable\Item;

use Application\Db\Table;
use Application\Model\DbTable;

use Exception;

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
        $brandItemRows = $this->fetchAll([
            'item_id = ?'   => $carId,
            'parent_id = ?' => $brandId
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
            'item_id = ?' => $carId
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
        $db = $this->getAdapter();

        $result = [];

        $brand = $db->fetchRow(
            $db->select()
                ->from('item', ['catname'])
                ->where('item_type_id = ?', DbTable\Item\Type::BRAND)
                ->where('id = ?', $carId)
        );

        if ($brand) {
            $result[] = [
                'brand_catname' => $brand['catname'],
                'car_catname'   => null,
                'path'          => []
            ];
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->fetchAll([
            'item_id = ?' => $carId
        ]);

        foreach ($parents as $parentRow) {
            $brand = $db->fetchRow(
                $db->select()
                    ->from('item', ['catname'])
                    ->where('item_type_id = ?', DbTable\Item\Type::BRAND)
                    ->where('id = ?', $parentRow['parent_id'])
            );

            if ($brand) {
                $result[] = [
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $parentRow['catname'],
                    'path'          => []
                ];
            }
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

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
