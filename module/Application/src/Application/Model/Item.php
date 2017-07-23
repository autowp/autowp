<?php

namespace Application\Model;

use DateTime;

use geoPHP;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Application\Model\DbTable;

use Zend_Db_Expr;

class Item
{
    /**
     * @var TableGateway
     */
    private $specTable;

    /**
     * @var DbTable\Item
     */
    private $itemTable;

    /**
     * @var TableGateway
     */
    private $itemPointTable;

    /**
     * @var TableGateway
     */
    private $vehicleTypeParentTable;

    public function __construct(
        TableGateway $specTable,
        TableGateway $itemPointTable,
        TableGateway $vehicleTypeParentTable
    ) {
        $this->specTable = $specTable;

        $this->itemTable = new DbTable\Item();
        $this->itemPointTable = $itemPointTable;
        $this->vehicleTypeParentTable = $vehicleTypeParentTable;
    }

    public function getEngineVehiclesGroups(int $engineId, array $options = [])
    {
        $defaults = [
            'groupJoinLimit' => null
        ];
        $options = array_replace($defaults, $options);

        $db = $this->itemTable->getAdapter();

        $vehicleIds = $db->fetchCol(
            $db->select()
                ->from('item', 'id')
                ->join('item_parent_cache', 'item.engine_item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $engineId)
        );

        $vectors = [];
        foreach ($vehicleIds as $vehicleId) {
            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('item_parent_cache', 'parent_id')
                    ->join('item', 'item_parent_cache.parent_id = item.id', null)
                    ->where('item.item_type_id = ?', DbTable\Item\Type::VEHICLE)
                    ->where('item_parent_cache.item_id = ?', $vehicleId)
                    ->where('item_parent_cache.item_id <> item_parent_cache.parent_id')
                    ->order('item_parent_cache.diff desc')
            );

            // remove parents
            foreach ($parentIds as $parentId) {
                $index = array_search($parentId, $vehicleIds);
                if ($index !== false) {
                    unset($vehicleIds[$index]);
                }
            }

            $vector = $parentIds;
            $vector[] = $vehicleId;

            $vectors[] = $vector;
        }

        if ($options['groupJoinLimit'] && count($vehicleIds) <= $options['groupJoinLimit']) {
            return $vehicleIds;
        }


        do {
            // look for same root

            $matched = false;
            for ($i = 0; ($i < count($vectors) - 1) && ! $matched; $i++) {
                for ($j = $i + 1; $j < count($vectors) && ! $matched; $j++) {
                    if ($vectors[$i][0] == $vectors[$j][0]) {
                        $matched = true;
                        // matched root
                        $newVector = [];
                        $length = min(count($vectors[$i]), count($vectors[$j]));
                        for ($k = 0; $k < $length && $vectors[$i][$k] == $vectors[$j][$k]; $k++) {
                            $newVector[] = $vectors[$i][$k];
                        }
                        $vectors[$i] = $newVector;
                        array_splice($vectors, $j, 1);
                    }
                }
            }
        } while ($matched);

        $resultIds = [];
        foreach ($vectors as $vector) {
            $resultIds[] = $vector[count($vector) - 1];
        }

        return $resultIds;
    }

    public function getName($itemId, $language)
    {
        $carLangTable = new DbTable\Item\Language();

        $db = $carLangTable->getAdapter();

        $languages = array_merge([$language], ['en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'zh', 'xx']);

        $select = $db->select()
            ->from('item_language', ['name'])
            ->where('item_id = ?', (int)$itemId)
            ->where('length(name) > 0')
            ->order(new Zend_Db_Expr($db->quoteInto('FIELD(language, ?)', $languages)))
            ->limit(1);

        return $db->fetchOne($select);
    }

    public function getNameData(\Autowp\Commons\Db\Table\Row $row, string $language = 'en')
    {
        /*$carLangTable = new DbTable\Item\Language();
         $carLangRow = $carLangTable->fetchRow([
         'item_id = ?'  => $this->id,
         'language = ?' => (string)$language
         ]);

         $name = $carLangRow && $carLangRow->name ? $carLangRow->name : $this->name;*/

        $name = $this->getName($row['id'], $language);

        $spec = null;
        $specFull = null;
        if ($row['spec_id']) {
            $specRow = $this->specTable->select(['id' => (int)$row['spec_id']])->current();
            if ($specRow) {
                $spec = $specRow['short_name'];
                $specFull = $specRow['name'];
            }
        }

        return [
            'begin_model_year' => $row['begin_model_year'],
            'end_model_year'   => $row['end_model_year'],
            'spec'             => $spec,
            'spec_full'        => $specFull,
            'body'             => $row['body'],
            'name'             => $name,
            'begin_year'       => $row['begin_year'],
            'end_year'         => $row['end_year'],
            'today'            => $row['today'],
            'begin_month'      => $row['begin_month'],
            'end_month'        => $row['end_month'],
        ];
    }

    public function getRelatedCarGroups(int $itemId): array
    {
        $db = $this->itemTable->getAdapter();

        $carIds = $db->fetchCol(
            $db->select()
                ->from('item_parent', 'item_id')
                ->where('item_parent.parent_id = ?', $itemId)
        );

        $vectors = [];
        foreach ($carIds as $carId) {
            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('item_parent_cache', 'parent_id')
                    ->join('item', 'item_parent_cache.parent_id = item.id', null)
                    ->where('item.item_type_id IN (?)', [
                        DbTable\Item\Type::VEHICLE,
                        DbTable\Item\Type::ENGINE
                    ])
                    ->where('item_parent_cache.item_id = ?', $carId)
                    ->where('item_parent_cache.item_id <> item_parent_cache.parent_id')
                    ->order('item_parent_cache.diff desc')
            );

            // remove parents
            foreach ($parentIds as $parentId) {
                $index = array_search($parentId, $carIds);
                if ($index !== false) {
                    unset($carIds[$index]);
                }
            }

            $vector = $parentIds;
            $vector[] = $carId;

            $vectors[] = [
                'parents' => $vector,
                'childs'  => [$carId]
            ];
        }

        do {
            // look for same root

            $matched = false;
            for ($i = 0; ($i < count($vectors) - 1) && ! $matched; $i++) {
                for ($j = $i + 1; $j < count($vectors) && ! $matched; $j++) {
                    if ($vectors[$i]['parents'][0] == $vectors[$j]['parents'][0]) {
                        $matched = true;
                        // matched root
                        $newVector = [];
                        $length = min(count($vectors[$i]['parents']), count($vectors[$j]['parents']));
                        for ($k = 0; $k < $length && $vectors[$i]['parents'][$k] == $vectors[$j]['parents'][$k]; $k++) {
                            $newVector[] = $vectors[$i]['parents'][$k];
                        }
                        $vectors[$i] = [
                            'parents' => $newVector,
                            'childs'  => array_merge($vectors[$i]['childs'], $vectors[$j]['childs'])
                        ];
                        array_splice($vectors, $j, 1);
                    }
                }
            }
        } while ($matched);

        $result = [];
        foreach ($vectors as $vector) {
            $carId = $vector['parents'][count($vector['parents']) - 1];
            $result[$carId] = $vector['childs'];
        }

        return $result;
    }

    public function getRelatedCarGroupId(int $itemId): array
    {
        $db = $this->itemTable->getAdapter();

        $carIds = $db->fetchCol(
            $db->select()
                ->from('item_parent', 'item_id')
                ->where('item_parent.parent_id = ?', $itemId)
        );

        $vectors = [];
        foreach ($carIds as $carId) {
            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('item_parent_cache', 'parent_id')
                    ->join('item', 'item_parent_cache.parent_id = item.id', null)
                    ->where('item.item_type_id IN (?)', [
                        DbTable\Item\Type::VEHICLE,
                        DbTable\Item\Type::ENGINE
                    ])
                    ->where('item_id = ?', $carId)
                    ->where('item_id <> parent_id')
                    ->order('diff desc')
            );

            // remove parents
            foreach ($parentIds as $parentId) {
                $index = array_search($parentId, $carIds);
                if ($index !== false) {
                    unset($carIds[$index]);
                }
            }

            $vector = $parentIds;
            $vector[] = $carId;

            $vectors[] = $vector;
        }

        do {
            // look for same root

            $matched = false;
            for ($i = 0; ($i < count($vectors) - 1) && ! $matched; $i++) {
                for ($j = $i + 1; $j < count($vectors) && ! $matched; $j++) {
                    if ($vectors[$i][0] == $vectors[$j][0]) {
                        $matched = true;
                        // matched root
                        $newVector = [];
                        $length = min(count($vectors[$i]), count($vectors[$j]));
                        for ($k = 0; $k < $length && $vectors[$i][$k] == $vectors[$j][$k]; $k++) {
                            $newVector[] = $vectors[$i][$k];
                        }
                        $vectors[$i] = $newVector;
                        array_splice($vectors, $j, 1);
                    }
                }
            }
        } while ($matched);

        $resultIds = [];
        foreach ($vectors as $vector) {
            $resultIds[] = $vector[count($vector) - 1];
        }

        return $resultIds;
    }

    public function updateOrderCache(int $itemId): bool
    {
        $row = $this->itemTable->find($itemId)->current();
        if (! $row) {
            return false;
        }

        $begin = null;
        if ($row['begin_year']) {
            $begin = new DateTime();
            $begin->setDate(
                $row['begin_year'],
                $row['begin_month'] ? $row['begin_month'] : 1,
                1
            );
        } elseif ($row['begin_model_year']) {
            $begin = new DateTime();
            $begin->setDate( // approximation
                $row['begin_model_year'] - 1,
                10,
                1
            );
        } else {
            $begin = new DateTime();
            $begin->setDate(
                2100,
                1,
                1
            );
        }

        $end = null;
        if ($row['end_year']) {
            $end = new DateTime();
            $end->setDate(
                $row['end_year'],
                $row['end_month'] ? $row['end_month'] : 12,
                1
            );
        } elseif ($row['end_model_year']) {
            $end = new DateTime();
            $end->setDate( // approximation
                $row['end_model_year'],
                9,
                30
            );
        } else {
            $end = $begin;
        }

        $row->setFromArray([
            'begin_order_cache' => $begin ? $begin->format(MYSQL_DATETIME_FORMAT) : null,
            'end_order_cache'   => $end ? $end->format(MYSQL_DATETIME_FORMAT) : null,
        ]);
        $row->save();

        return true;
    }

    private function getChildVehicleTypesByWhitelist($parentId, array $whitelist): array
    {
        if (count($whitelist) <= 0) {
            return [];
        }

        $select = new Sql\Select($this->vehicleTypeParentTable->getTable());
        $select->columns(['id'])
            ->where([
                new Sql\Predicate\In('id', $whitelist),
                'parent_id' => $parentId,
                'id <> parent_id'
            ]);

        $result = [];
        foreach ($this->vehicleTypeParentTable->selectWith($select) as $row) {
            $result[] = (int)$row['id'];
        }

        return $result;
    }

    public function updateInteritance(\Autowp\Commons\Db\Table\Row $car)
    {
        $parents = $this->itemTable->fetchAll(
            $this->itemTable->select(true)
                ->join('item_parent', 'item.id = item_parent.parent_id', null)
                ->where('item_parent.item_id = ?', $car->id)
        );

        $somethingChanged = false;

        if ($car->is_concept_inherit) {
            $isConcept = false;
            foreach ($parents as $parent) {
                if ($parent->is_concept) {
                    $isConcept = true;
                }
            }

            $oldIsConcept = (bool)$car->is_concept;

            if ($oldIsConcept !== $isConcept) {
                $car->is_concept = $isConcept ? 1 : 0;
                $somethingChanged = true;
            }
        }

        if ($car->engine_inherit) {
            $map = [];
            foreach ($parents as $parent) {
                $engineId = $parent->engine_item_id;
                if ($engineId) {
                    if (isset($map[$engineId])) {
                        $map[$engineId]++;
                    } else {
                        $map[$engineId] = 1;
                    }
                }
            }

            // select top
            $maxCount = null;
            $selectedId = null;
            foreach ($map as $id => $count) {
                if (is_null($maxCount) || ($count > $maxCount)) {
                    $maxCount = $count;
                    $selectedId = (int)$id;
                }
            }

            $oldEngineId = isset($car->engine_item_id) ? (int)$car->engine_item_id : null;

            if ($oldEngineId !== $selectedId) {
                $car->engine_item_id = $selectedId;
                $somethingChanged = true;
            }
        }

        if ($car->car_type_inherit) {
            $map = [];
            foreach ($parents as $parent) {
                $typeId = $parent->car_type_id;
                if ($typeId) {
                    if (isset($map[$typeId])) {
                        $map[$typeId]++;
                    } else {
                        $map[$typeId] = 1;
                    }
                }
            }

            foreach ($map as $id => $count) {
                $otherIds = array_diff(array_keys($map), [$id]);

                $isParentOf = $this->getChildVehicleTypesByWhitelist($id, $otherIds);

                if (count($isParentOf)) {
                    foreach ($isParentOf as $childId) {
                        $map[$childId] += $count;
                    }

                    unset($map[$id]);
                }
            }

            // select top
            $maxCount = null;
            $selectedId = null;
            foreach ($map as $id => $count) {
                if (is_null($maxCount) || ($count > $maxCount)) {
                    $maxCount = $count;
                    $selectedId = (int)$id;
                }
            }

            $oldCarTypeId = isset($car->car_type_id) ? (int)$car->car_type_id : null;

            if ($oldCarTypeId !== $selectedId) {
                $car->car_type_id = $selectedId;
                $somethingChanged = true;
            }
        }

        if ($car->spec_inherit) {
            $map = [];
            foreach ($parents as $parent) {
                $specId = $parent->spec_id;
                if ($specId) {
                    if (isset($map[$specId])) {
                        $map[$specId]++;
                    } else {
                        $map[$specId] = 1;
                    }
                }
            }

            // select top
            $maxCount = null;
            $selectedId = null;
            foreach ($map as $id => $count) {
                if (is_null($maxCount) || ($count > $maxCount)) {
                    $maxCount = $count;
                    $selectedId = (int)$id;
                }
            }

            $oldSpecId = isset($car->spec_id) ? (int)$car->spec_id : null;

            if ($oldSpecId !== $selectedId) {
                $car->spec_id = $selectedId;
                $somethingChanged = true;
            }
        }

        if ($somethingChanged || ! $car->car_type_inherit) {
            $car->save();

            $childs = $this->itemTable->fetchAll(
                $this->itemTable->select(true)
                    ->join('item_parent', 'item.id = item_parent.item_id', null)
                    ->where('item_parent.parent_id = ?', $car->id)
            );

            foreach ($childs as $child) {
                $this->updateInteritance($child);
            }
        }
    }

    public function getVehiclesAndEnginesCount(int $parentId): int
    {
        $db = $this->itemTable->getAdapter();

        $select = $db->select()
            ->from('item', new Zend_Db_Expr('COUNT(1)'))
            ->where('item.item_type_id IN (?)', [
                DbTable\Item\Type::ENGINE,
                DbTable\Item\Type::VEHICLE
            ])
            ->where('not item.is_group')
            ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $parentId);

        return (int)$db->fetchOne($select);
    }

    public function setPoint(int $itemId, $point)
    {
        $primaryKey = ['item_id' => $itemId];

        if (! $point) {
            $this->itemPointTable->delete($primaryKey);
            return;
        }

        $set = [
            'point' => new Sql\Expression('GeomFromText(?)', [$point->out('wkt')])
        ];

        $row = $this->itemPointTable->select($primaryKey)->current();
        if ($row) {
            $this->itemPointTable->update($set, $primaryKey);
            return;
        }
        $this->itemPointTable->insert(array_replace($set, $primaryKey));
    }

    public function getPoint(int $itemId)
    {
        $point = null;
        $row = $this->itemPointTable->select(['item_id' => $itemId])->current();
        if ($row && $row['point']) {
            geoPHP::version(); // for autoload classes
            $point = geoPHP::load(substr($row['point'], 4), 'wkb');
        }

        return $point;
    }
}
