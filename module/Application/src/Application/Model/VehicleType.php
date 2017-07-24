<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

class VehicleType
{
    /**
     * @var TableGateway
     */
    private $vehicleTypeTable;

    /**
     * @var TableGateway
     */
    private $itemVehicleTypeTable;

    /**
     * @var TableGateway
     */
    private $itemParentTable;

    /**
     * @var TableGateway
     */
    private $vehicleTypeParentTable;

    public function __construct(
        TableGateway $itemVehicleTypeTable,
        TableGateway $itemParentTable,
        TableGateway $vehicleTypeParentTable,
        TableGateway $vehicleTypeTable
    ) {
        $this->vehicleTypeTable = $vehicleTypeTable;
        $this->itemVehicleTypeTable = $itemVehicleTypeTable;
        $this->itemParentTable = $itemParentTable;
        $this->vehicleTypeParentTable = $vehicleTypeParentTable;
    }

    public function removeVehicleType(int $vehicleId, int $type)
    {
        $deleted = $this->itemVehicleTypeTable->delete([
            'vehicle_id = ?'      => $vehicleId,
            'vehicle_type_id = ?' => $type,
            'not inherited'
        ]);

        if ($deleted > 0) {
            $this->refreshInheritance($vehicleId);
        }
    }

    public function addVehicleType(int $vehicleId, int $type)
    {
        $changed = $this->setRow($vehicleId, $type, false);

        if ($changed) {
            $this->refreshInheritance($vehicleId);
        }
    }

    public function setVehicleTypes(int $vehicleId, array $types)
    {
        $inherited = false;

        if (! $types) {
            $types = $this->getInheritedIds($vehicleId);
            $inherited = true;
        }

        $changed = $this->setRows($vehicleId, $types, $inherited);

        if ($changed) {
            $this->refreshInheritance($vehicleId);
        }
    }

    private function setRows(int $vehicleId, array $types, bool $inherited): bool
    {
        $changed = false;

        foreach ($types as $type) {
            $rowChanged = $this->setRow($vehicleId, $type, $inherited);
            if ($rowChanged) {
                $changed = true;
            }
        }

        $filter = [
            'vehicle_id = ?' => $vehicleId
        ];
        if ($types) {
            $filter[] = new Sql\Predicate\In('vehicle_type_id', $types);
        }

        $deleted = $this->itemVehicleTypeTable->delete($filter);
        if ($deleted > 0) {
            $changed = true;
        }

        return $changed;
    }

    private function setRow(int $vehicleId, int $type, bool $inherited): bool
    {
        $primaryKey = [
            'vehicle_id'      => $vehicleId,
            'vehicle_type_id' => $type
        ];

        $row = $this->itemVehicleTypeTable->select($primaryKey)->current();
        if (! $row) {
            $this->itemVehicleTypeTable->insert(array_replace([
                'inherited' => $inherited ? 1 : 0
            ], $primaryKey));
            return true;
        }

        if ($inherited !== (bool)$row['inherited']) {
            $this->itemVehicleTypeTable->update([
                'inherited' => $inherited ? 1 : 0
            ], $primaryKey);

            return true;
        }

        return false;
    }

    private function getInheritedIds(int $vehicleId): array
    {
        $select = new Sql\Select($this->itemVehicleTypeTable->getTable());
        $select->columns(['vehicle_type_id'])
            ->quantifier($select::QUANTIFIER_DISTINCT)
            ->join('item_parent', 'vehicle_vehicle_type.vehicle_id = item_parent.parent_id', [])
            ->where(['item_parent.item_id' => $vehicleId]);

        $result = [];
        foreach ($this->itemVehicleTypeTable->selectWith($select) as $row) {
            $result[] = (int)$row['vehicle_type_id'];
        }

        return $result;
    }

    public function refreshInheritanceFromParents(int $vehicleId)
    {
        $typeIds = $this->getVehicleTypes($vehicleId);

        $this->setVehicleTypes($vehicleId, $typeIds);
    }

    public function refreshInheritance(int $vehicleId): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['item_id'])
            ->where(['parent_id' => $vehicleId]);

        $result = [];
        foreach ($this->itemParentTable->selectWith($select) as $row) {
            $result[] = (int)$row['item_id'];
        }

        return $result;
    }

    public function getVehicleTypes(int $vehicleId): array
    {
        $select = new Sql\Select($this->itemVehicleTypeTable->getTable());
        $select->columns(['vehicle_type_id'])
            ->where([
                'vehicle_id' => $vehicleId,
                'not inherited'
            ]);

        $result = [];
        foreach ($this->itemVehicleTypeTable->selectWith($select) as $row) {
            $result[] = (int)$row['vehicle_type_id'];
        }

        return $result;
    }

    public function getItemRow(int $vehicleId, int $typeId)
    {
        $row = $this->itemVehicleTypeTable->select([
            'vehicle_id'      => $vehicleId,
            'vehicle_type_id' => $typeId
        ])->current();

        if (! $row) {
            return null;
        }

        return [
            'item_id'         => (int)$row['vehicle_id'],
            'vehicle_type_id' => (int)$row['vehicle_type_id'],
        ];
    }

    public function getItemSelect(int $vehicleId, int $typeId)
    {
        $select = new Sql\Select($this->itemVehicleTypeTable->getTable());
        if ($vehicleId) {
            $select->where(['vehicle_id' => $vehicleId]);
        }
        if ($typeId) {
            $select->where(['vehicle_type_id' => $typeId]);
        }

        return $select;
    }

    /**
     * @return TableGateway
     */
    public function getItemTable()
    {
        return $this->itemVehicleTypeTable;
    }

    public function rebuildParents()
    {
        $this->delete([]);

        $this->rebuildStep([0], 0);
    }

    private function rebuildStep(array $id, int $level)
    {
        $select = new Sql\Select($this->vehicleTypeTable->getTable());
        $select->columns(['id']);

        if ($id[0] == 0) {
            $select->where(['parent_id is null']);
        } else {
            $select->where(['parent_id' => $id[0]]);
        }

        foreach ($this->vehicleTypeTable->selectWith($select) as $row) {
            $this->vehicleTypeParentTable->insert([
                'id'        => $row['id'],
                'parent_id' => $row['id'],
                'level'     => $level
            ]);

            $this->rebuildStep(array_merge([$row['id']], $id), $level + 1);
        }

        --$level;
        foreach ($id as $tid) {
            if ($tid && ( $id[0] != $tid )) {
                $this->vehicleTypeParentTable->insert([
                    'id'        => $id[0],
                    'parent_id' => $tid,
                    'level'     => --$level
                ]);
            }
        }
    }

    public function getRowByCatname(string $catname)
    {
        return $this->vehicleTypeTable->select([
            'catname' => $catname
        ])->current();
    }

    public function getDescendantsAndSelfIds(int $parentId): array
    {
        $select = new Sql\Select($this->vehicleTypeParentTable->getTable());
        $select->columns(['id'])
            ->where(['parent_id' => $parentId]);

        $result = [];
        foreach ($this->vehicleTypeParentTable->selectWith($select) as $row) {
            $result[] = $row['id'];
        }

        return $result;
    }

    public function getRows(int $parentId, int $brandId): array
    {
        $select = new Sql\Select($this->vehicleTypeTable->getTable());

        $select->order('car_types.position');

        if ($parentId) {
            $select->where(['car_types.parent_id' => $parentId]);
        } else {
            $select->where(['car_types.parent_id IS NULL']);
        }

        if ($brandId) {
            $select
                ->join('car_types_parents', 'car_types.id = car_types_parents.parent_id', [])
                ->join('vehicle_vehicle_type', 'car_types_parents.id = vehicle_vehicle_type.vehicle_type_id', [])
                ->join('item_parent_cache', 'vehicle_vehicle_type.vehicle_id = item_parent_cache.item_id', [])
                ->where(['item_parent_cache.parent_id' => $brandId])
                ->group('car_types.id');
        }

        $result = [];
        foreach ($this->vehicleTypeTable->selectWith($select) as $row) {
            $result[] = $row;
        }

        return $result;
    }

    public function getTree(int $parentId = 0): array
    {
        if ($parentId) {
            $filter = ['parent_id' => $parentId];
        } else {
            $filter = 'parent_id is null';
        }

        $select = new Sql\Select($this->vehicleTypeTable->getTable());
        $select->columns(['id', 'name'])
            ->where($filter)
            ->order('position');

        $result = [];
        foreach ($this->vehicleTypeTable->selectWith($select) as $row) {
            $result[] = [
                'id'     => (int)$row['id'],
                'name'   => $row['name'],
                'childs' => $this->getTree($row['id'])
            ];
        }

        return $result;
    }

    public function getBrandVehicleTypes(int $brandId): array
    {
        $select = new Sql\Select($this->vehicleTypeTable->getTable());

        $select->columns([
                'id',
                'name',
                'catname',
                'cars_count' => new Sql\Expression('COUNT(1)')
            ])
            ->join('vehicle_vehicle_type', 'car_types.id = vehicle_vehicle_type.vehicle_type_id', [])
            ->join('item', 'vehicle_vehicle_type.vehicle_id = item.id', [])
            ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', [])
            ->where([
                'item_parent_cache.parent_id' => $brandId,
                'item.begin_year or item.begin_model_year',
                'not item.is_group'
            ])
            ->group('car_types.id')
            ->order('car_types.position');

        $result = [];
        foreach ($this->vehicleTypeTable->selectWith($select) as $row) {
            $result[] = [
                'id'        => (int)$carType['id'],
                'name'      => $carType['name'],
                'catname'   => $carType['catname'],
                'carsCount' => (int)$row['cars_count']
            ];
        }

        return $result;
    }
}
