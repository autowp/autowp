<?php

namespace Application\Model;

use ArrayObject;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function array_merge;
use function count;
use function is_array;

class VehicleType
{
    private TableGateway $vehicleTypeTable;

    private TableGateway $itemVehicleTypeTable;

    private TableGateway $itemParentTable;

    private TableGateway $vehicleTypeParentTable;

    public function __construct(
        TableGateway $itemVehicleTypeTable,
        TableGateway $itemParentTable,
        TableGateway $vehicleTypeParentTable,
        TableGateway $vehicleTypeTable
    ) {
        $this->vehicleTypeTable       = $vehicleTypeTable;
        $this->itemVehicleTypeTable   = $itemVehicleTypeTable;
        $this->itemParentTable        = $itemParentTable;
        $this->vehicleTypeParentTable = $vehicleTypeParentTable;
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function removeVehicleType(int $vehicleId, int $type): void
    {
        $deleted = $this->itemVehicleTypeTable->delete([
            'vehicle_id = ?'      => $vehicleId,
            'vehicle_type_id = ?' => $type,
            'not inherited',
        ]);

        if ($deleted > 0) {
            $this->refreshInheritanceFromParents($vehicleId);
            $this->refreshInheritance($vehicleId);
        }
    }

    public function addVehicleType(int $vehicleId, int $type)
    {
        $changed = $this->setRow($vehicleId, $type, false);

        if ($changed) {
            $this->refreshInheritanceFromParents($vehicleId);
            $this->refreshInheritance($vehicleId);
        }
    }

    public function setVehicleTypes(int $vehicleId, array $types)
    {
        $inherited = false;

        if (! $types) {
            $types     = $this->getInheritedIds($vehicleId);
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
            'vehicle_id' => $vehicleId,
        ];
        if ($types) {
            $filter[] = new Sql\Predicate\NotIn('vehicle_type_id', $types);
        }

        $deleted = $this->itemVehicleTypeTable->delete($filter);
        if ($deleted > 0) {
            $changed = true;
        }

        return $changed;
    }

    private function setRow(int $vehicleId, int $type, bool $inherited): bool
    {
        $sql = '
            INSERT INTO vehicle_vehicle_type (vehicle_id, vehicle_type_id, inherited)
            VALUES (:vehicle_id, :vehicle_type_id, :inherited)
            ON DUPLICATE KEY UPDATE inherited = VALUES(inherited)
        ';
        /** @var ResultInterface $result */
        $result = $this->itemVehicleTypeTable->getAdapter()->query($sql, [
            'vehicle_id'      => $vehicleId,
            'vehicle_type_id' => $type,
            'inherited'       => $inherited ? 1 : 0,
        ]);

        return $result->getAffectedRows() > 0;
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
            $result[] = (int) $row['vehicle_type_id'];
        }

        return $result;
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function refreshInheritanceFromParents(int $vehicleId): void
    {
        $typeIds = $this->getVehicleTypes($vehicleId);

        if ($typeIds) {
            // do not inherit when own value
            $affected = $this->itemVehicleTypeTable->delete([
                'vehicle_id' => $vehicleId,
                'inherited',
            ]);
            if ($affected > 0) {
                $this->refreshInheritance($vehicleId);
            }
            return;
        }

        $types = $this->getInheritedIds($vehicleId);

        $changed = $this->setRows($vehicleId, $types, true);

        if ($changed) {
            $this->refreshInheritance($vehicleId);
        }
    }

    private function refreshInheritance(int $vehicleId)
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['item_id'])
            ->where(['parent_id' => $vehicleId]);

        foreach ($this->itemParentTable->selectWith($select) as $row) {
            $this->refreshInheritanceFromParents($row['item_id']);
        }
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function getVehicleTypes(int $vehicleId, bool $inherited = false): array
    {
        $select = new Sql\Select($this->itemVehicleTypeTable->getTable());
        $select->columns(['vehicle_type_id'])
            ->where([
                'vehicle_id' => $vehicleId,
                $inherited ? 'inherited' : 'not inherited',
            ]);

        $result = [];
        foreach ($this->itemVehicleTypeTable->selectWith($select) as $row) {
            $result[] = (int) $row['vehicle_type_id'];
        }

        return $result;
    }

    public function getItemRow(int $vehicleId, int $typeId): ?array
    {
        $row = $this->itemVehicleTypeTable->select([
            'vehicle_id'      => $vehicleId,
            'vehicle_type_id' => $typeId,
        ])->current();

        if (! $row) {
            return null;
        }

        return [
            'item_id'         => (int) $row['vehicle_id'],
            'vehicle_type_id' => (int) $row['vehicle_type_id'],
        ];
    }

    public function getItemSelect(int $vehicleId, int $typeId): Sql\Select
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

    public function getItemTable(): TableGateway
    {
        return $this->itemVehicleTypeTable;
    }

    public function rebuildParents(): void
    {
        $this->vehicleTypeParentTable->delete([]);

        $this->rebuildStep([0], 0);
    }

    private function rebuildStep(array $id, int $level): void
    {
        $select = new Sql\Select($this->vehicleTypeTable->getTable());
        $select->columns(['id']);

        if ($id[0] === 0) {
            $select->where(['parent_id is null']);
        } else {
            $select->where(['parent_id' => $id[0]]);
        }

        foreach ($this->vehicleTypeTable->selectWith($select) as $row) {
            $this->vehicleTypeParentTable->insert([
                'id'        => $row['id'],
                'parent_id' => $row['id'],
                'level'     => $level,
            ]);

            $this->rebuildStep(array_merge([(int) $row['id']], $id), $level + 1);
        }

        --$level;
        foreach ($id as $tid) {
            if ($tid && ( $id[0] !== $tid )) {
                $this->vehicleTypeParentTable->insert([
                    'id'        => $id[0],
                    'parent_id' => $tid,
                    'level'     => --$level,
                ]);
            }
        }
    }

    /**
     * @return array|ArrayObject
     */
    public function getRowByCatname(string $catname)
    {
        return $this->vehicleTypeTable->select([
            'catname' => $catname,
        ])->current();
    }

    /**
     * @param array|int $parentId
     */
    public function getDescendantsAndSelfIds($parentId): array
    {
        $select = new Sql\Select($this->vehicleTypeParentTable->getTable());
        $select->columns(['id']);

        if (is_array($parentId)) {
            if (count($parentId) <= 0) {
                return [];
            }

            $select->where([new Sql\Predicate\In('parent_id', $parentId)]);
        } else {
            $select->where(['parent_id' => $parentId]);
        }

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
                'id'     => (int) $row['id'],
                'name'   => $row['name'],
                'childs' => $this->getTree($row['id']),
            ];
        }

        return $result;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    public function getBrandVehicleTypes(int $brandId): array
    {
        $select = new Sql\Select($this->vehicleTypeTable->getTable());

        $select->columns([
            'id',
            'name',
            'catname',
            'cars_count' => new Sql\Expression('COUNT(DISTINCT item.id)'),
        ])
            ->join('vehicle_vehicle_type', 'car_types.id = vehicle_vehicle_type.vehicle_type_id', [])
            ->join('item', 'vehicle_vehicle_type.vehicle_id = item.id', [])
            ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', [])
            ->where([
                'item_parent_cache.parent_id' => $brandId,
                '(item.begin_year or item.begin_model_year)',
                'not item.is_group',
            ])
            ->group('car_types.id')
            ->order('car_types.position');

        $result = [];
        foreach ($this->vehicleTypeTable->selectWith($select) as $row) {
            $result[] = [
                'id'        => (int) $row['id'],
                'name'      => $row['name'],
                'catname'   => $row['catname'],
                'carsCount' => (int) $row['cars_count'],
            ];
        }

        return $result;
    }
}
