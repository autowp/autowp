<?php

namespace Application\Model;

use ArrayObject;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;
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
        /** @var Adapter $adapter */
        $adapter = $this->itemVehicleTypeTable->getAdapter();
        $stmt    = $adapter->createStatement('
            INSERT INTO vehicle_vehicle_type (vehicle_id, vehicle_type_id, inherited)
            VALUES (:vehicle_id, :vehicle_type_id, :inherited)
            ON DUPLICATE KEY UPDATE inherited = VALUES(inherited)
        ');
        $result  = $stmt->execute([
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

    private function refreshInheritance(int $vehicleId): void
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['item_id'])
            ->where(['parent_id' => $vehicleId]);

        foreach ($this->itemParentTable->selectWith($select) as $row) {
            $this->refreshInheritanceFromParents($row['item_id']);
        }
    }

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

    /**
     * @return array|ArrayObject|null
     * @throws Exception
     */
    public function getRowByCatname(string $catname)
    {
        return currentFromResultSetInterface($this->vehicleTypeTable->select([
            'catname' => $catname,
        ]));
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
}
