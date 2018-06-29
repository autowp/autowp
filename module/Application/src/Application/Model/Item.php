<?php

namespace Application\Model;

use DateTime;
use Exception;

use geoPHP;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Paginator;
use Zend\Router\Http\TreeRouteStack;

use Autowp\TextStorage\Service as TextStorage;

class Item
{
    const VEHICLE   = 1,
          ENGINE    = 2,
          CATEGORY  = 3,
          TWINS     = 4,
          BRAND     = 5,
          FACTORY   = 6,
          MUSEUM    = 7,
          PERSON    = 8,
          COPYRIGHT = 9;

    const MAX_NAME = 100;

    /**
     * @var TableGateway
     */
    private $specTable;

    /**
     * @var TableGateway
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

    /**
     * @var TableGateway
     */
    private $itemLanguageTable;

    /**
     * @var TextStorage
     */
    private $textStorage;

    /**
     * @var TableGateway
     */
    private $itemParentCacheTable;

    /**
     * @var TableGateway
     */
    private $itemParentTable;

    /**
     * @var TableGateway
     */
    private $itemParentLanguageTable;

    /**
     * @var LanguagePriority
     */
    private $languagePriority;

    public function __construct(
        TableGateway $specTable,
        TableGateway $itemPointTable,
        TableGateway $vehicleTypeParentTable,
        TableGateway $itemLanguageTable,
        TextStorage $textStorage,
        TableGateway $itemTable,
        TableGateway $itemParentTable,
        TableGateway $itemParentLanguageTable,
        TableGateway $itemParentCacheTable
    ) {
        $this->specTable = $specTable;
        $this->itemTable = $itemTable;
        $this->itemPointTable = $itemPointTable;
        $this->vehicleTypeParentTable = $vehicleTypeParentTable;
        $this->itemLanguageTable = $itemLanguageTable;
        $this->textStorage = $textStorage;
        $this->itemParentTable = $itemParentTable;
        $this->itemParentLanguageTable = $itemParentLanguageTable;
        $this->itemParentCacheTable = $itemParentCacheTable;

        $this->languagePriority = new LanguagePriority();
    }

    public function getEngineVehiclesGroups(int $engineId, array $options = []): array
    {
        $defaults = [
            'groupJoinLimit' => null
        ];
        $options = array_replace($defaults, $options);

        $select = new Sql\Select($this->itemTable->getTable());
        $select->columns(['id'])
            ->join('item_parent_cache', 'item.engine_item_id = item_parent_cache.item_id', [])
            ->where(['item_parent_cache.parent_id' => $engineId]);
        $vehicleIds = [];
        foreach ($this->itemTable->selectWith($select) as $row) {
            $vehicleIds[] = (int)$row['id'];
        }

        $vectors = [];
        foreach ($vehicleIds as $vehicleId) {
            $select = new Sql\Select($this->itemParentCacheTable->getTable());
            $select->columns(['parent_id'])
                ->join('item', 'item_parent_cache.parent_id = item.id', [])
                ->where([
                    'item.item_type_id'         => self::VEHICLE,
                    'item_parent_cache.item_id' => $vehicleId,
                    'item_parent_cache.item_id != item_parent_cache.parent_id'
                ])
                ->order('item_parent_cache.diff desc');
            $parentIds = [];
            foreach ($this->itemParentCacheTable->selectWith($select) as $row) {
                $parentIds[] = (int)$row['parent_id'];
            }

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

    public function setLanguageName(int $id, string $language, string $name)
    {
        $primaryKey = [
            'item_id'  => $id,
            'language' => $language
        ];
        $set = [
            'name' => $name
        ];

        $row = $this->itemLanguageTable->select($primaryKey)->current();

        if (! $row) {
            $this->itemLanguageTable->insert(array_replace($set, $primaryKey));
            return;
        }

        $this->itemLanguageTable->update($set, $primaryKey);
    }

    public function getUsedLanguagesCount(int $id): int
    {
        $select = new Sql\Select($this->itemLanguageTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where([
                'item_id'       => $id,
                'language != ?' => 'xx'
            ]);

        $row = $this->itemLanguageTable->selectWith($select)->current();
        return $row ? (int)$row['count'] : 0;
    }

    public function getLanguageNamesOfItems(array $ids, string $language): array
    {
        if (! $ids) {
            return [];
        }

        $rows = $this->itemLanguageTable->select([
            new Sql\Predicate\In('item_id', $ids),
            'language' => $language,
            new Sql\Predicate\Expression('length(name) > 0')
        ]);
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['item_id']] = $row['name'];
        }

        return $result;
    }

    public function getTextsOfItem(int $id, string $language): array
    {
        $select = new Sql\Select($this->itemLanguageTable->getTable());

        $select
            ->where(['item_id' => $id])
            ->order([new Sql\Expression('language = ? desc', [$language])]);

        $rows = $this->itemLanguageTable->selectWith($select);

        $textIds = [];
        $fullTextIds = [];
        foreach ($rows as $row) {
            if ($row['text_id']) {
                $textIds[] = $row['text_id'];
            }
            if ($row['full_text_id']) {
                $fullTextIds[] = $row['full_text_id'];
            }
        }

        $description = null;
        if ($textIds) {
            $description = $this->textStorage->getFirstText($textIds);
        }

        $text = null;
        if ($fullTextIds) {
            $text = $this->textStorage->getFirstText($fullTextIds);
        }

        return [
            'full_text' => $text,
            'text'      => $description
        ];
    }

    public function getTextOfItem(int $id, string $language): string
    {
        $select = new Sql\Select($this->itemLanguageTable->getTable());

        $orderBy = $this->languagePriority->getOrderByExpression($language, $this->itemLanguageTable->getAdapter());

        $select
            ->columns(['text_id'])
            ->where([
                'item_id' => $id,
                new Sql\Predicate\IsNotNull('text_id')
            ])
            ->order([new Sql\Expression($orderBy)]);

        $rows = $this->itemLanguageTable->selectWith($select);

        $textIds = [];
        foreach ($rows as $row) {
            $textIds[] = $row['text_id'];
        }

        $text = null;
        if ($textIds) {
            $text = $this->textStorage->getFirstText($textIds);
        }

        return $text ? $text : '';
    }

    public function hasFullText(int $id): bool
    {
        $rows = $this->itemLanguageTable->select([
            'item_id' => $id,
            new Sql\Predicate\IsNotNull('full_text_id')
        ]);

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = $row['full_text_id'];
        }

        if (! $ids) {
            return false;
        }

        return (bool)$this->textStorage->getFirstText($ids);
    }

    public function getNames(int $itemId): array
    {
        $rows = $this->itemLanguageTable->select([
            'item_id' => $itemId,
            new Sql\Predicate\Expression('length(name) > 0')
        ]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['language']] = $row['name'];
        }

        return $result;
    }

    public function getLanguageName(int $itemId, string $language): string
    {
        $select = new Sql\Select($this->itemLanguageTable->getTable());
        $select->columns(['name'])
            ->where([
                'item_id'  => $itemId,
                'language' => $language
            ]);

        $row = $this->itemLanguageTable->selectWith($select)->current();

        return $row ? $row['name'] : '';
    }

    public function getName(int $itemId, string $language)
    {
        $select = $this->getNameSelect($itemId, Sql\ExpressionInterface::TYPE_VALUE, $language);

        $row = $this->itemLanguageTable->selectWith($select)->current();

        return $row ? $row['name'] : '';
    }

    public function getNameData($row, string $language = 'en')
    {
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

    private function getAncestorsId(int $itemId, array $itemTypes): array
    {
        $select = new Sql\Select($this->itemParentCacheTable->getTable());

        $select->columns(['parent_id'])
            ->join('item', 'item_parent_cache.parent_id = item.id', [])
            ->where([
                new Sql\Predicate\In('item.item_type_id', $itemTypes),
                'item_parent_cache.item_id' => $itemId,
                'item_parent_cache.item_id != item_parent_cache.parent_id'
            ])
            ->order('item_parent_cache.diff desc');
        $parentIds = [];
        foreach ($this->itemParentCacheTable->selectWith($select) as $row) {
            $parentIds[] = (int)$row['parent_id'];
        }

        return $parentIds;
    }

    private function getChildItemsId(int $itemId): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['item_id'])
            ->where(['item_parent.parent_id' => $itemId]);

        $result = [];
        foreach ($this->itemParentTable->selectWith($select) as $row) {
            $result[] = (int)$row['item_id'];
        }

        return $result;
    }

    public function getRelatedCarGroups(int $itemId): array
    {
        $carIds = $this->getChildItemsId($itemId);

        $vectors = [];
        foreach ($carIds as $carId) {
            $parentIds = $this->getAncestorsId($carId, [
                self::VEHICLE,
                self::ENGINE
            ]);

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
        $carIds = $this->getChildItemsId($itemId);

        $vectors = [];
        foreach ($carIds as $carId) {
            $parentIds = $this->getAncestorsId($carId, [
                self::VEHICLE,
                self::ENGINE
            ]);

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
        $primaryKey = ['id' => $itemId];

        $row = $this->itemTable->select($primaryKey)->current();
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

        $this->itemTable->update([
            'begin_order_cache' => $begin ? $begin->format(MYSQL_DATETIME_FORMAT) : null,
            'end_order_cache'   => $end ? $end->format(MYSQL_DATETIME_FORMAT) : null,
        ], $primaryKey);

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

    public function updateInteritance(int $itemId)
    {
        $item = $this->itemTable->select(['id' => $itemId])->current();
        if (! $item) {
            throw new Exception("Item `$itemId` not found");
        }

        $this->updateItemInteritance($item);
    }

    private function updateItemInteritance($car)
    {
        $parents = $this->getRows([
            'child' => $car['id']
        ]);

        $somethingChanged = false;

        $set = [];

        if ($car['is_concept_inherit']) {
            $isConcept = false;
            foreach ($parents as $parent) {
                if ($parent['is_concept']) {
                    $isConcept = true;
                }
            }

            $oldIsConcept = (bool)$car['is_concept'];

            if ($oldIsConcept !== $isConcept) {
                $set['is_concept'] = $isConcept ? 1 : 0;
                $somethingChanged = true;
            }
        }

        if ($car['engine_inherit']) {
            $map = [];
            foreach ($parents as $parent) {
                $engineId = $parent['engine_item_id'];
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

            $oldEngineId = isset($car['engine_item_id']) ? (int)$car['engine_item_id'] : null;

            if ($oldEngineId !== $selectedId) {
                $set['engine_item_id'] = $selectedId;
                $somethingChanged = true;
            }
        }

        if ($car['car_type_inherit']) {
            $map = [];
            foreach ($parents as $parent) {
                $typeId = $parent['car_type_id'];
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

            $oldCarTypeId = isset($car['car_type_id']) ? (int)$car['car_type_id'] : null;

            if ($oldCarTypeId !== $selectedId) {
                $set['car_type_id'] = $selectedId;
                $somethingChanged = true;
            }
        }

        if ($car['spec_inherit']) {
            $map = [];
            foreach ($parents as $parent) {
                $specId = $parent['spec_id'];
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

            $oldSpecId = isset($car['spec_id']) ? (int)$car['spec_id'] : null;

            if ($oldSpecId !== $selectedId) {
                $set['spec_id'] = $selectedId;
                $somethingChanged = true;
            }
        }

        if ($somethingChanged || ! $car['car_type_inherit']) {
            if ($set) {
                $this->itemTable->update($set, [
                    'id' => $car['id']
                ]);
            }

            $childItems = $this->getRows([
                'parent' => $car['id']
            ]);

            foreach ($childItems as $child) {
                $this->updateItemInteritance($child);
            }
        }
    }

    public function getVehiclesAndEnginesCount(int $parentId): int
    {
        return $this->getCount([
            'item_type_id' => [self::ENGINE, self::VEHICLE],
            'ancestor'     => $parentId,
            'is_group'     => false
        ]);
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

    public function getTable(): TableGateway
    {
        return $this->itemTable;
    }

    private function applyColumns(array $columns, string $itemParentAlias, $language)
    {
        $result = [];

        foreach ($columns as $key => $column) {
            switch ($column) {
                case 'parent_id':
                    if (is_numeric($key)) {
                        $result[] = $column;
                    } else {
                        $result[$key] = $column;
                    }
                    break;
                case 'link_catname':
                    if (is_numeric($key)) {
                        $result[] = 'catname';
                    } else {
                        $result[$key] = 'catname';
                    }
                    break;
                case 'link_name':
                    if (! $language) {
                        throw new \Exception("Language is required for `name` select");
                    }
                    $nameSelect = $this->getItemParentNameSelect(
                        $itemParentAlias,
                        $language
                    );
                    if (is_numeric($key)) {
                        $result['link_name'] = $nameSelect;
                    } else {
                        $result[$key] = $nameSelect;
                    }
                    break;
                case 'link_type':
                    if (is_numeric($key)) {
                        $result['link_type'] = 'type';
                    } else {
                        $result[$key] = 'type';
                    }
                    break;
            }
        }

        return $result;
    }

    private function applyChildFilters(Sql\Select $select, $options, $prefix, $language, string $id): array
    {
        if (! is_array($options)) {
            $options = [
                'id' => $options
            ];
        }

        $alias = $prefix.'ip2';

        $columns = [];

        if (isset($options['columns']) && $options['columns']) {
            $columns = $this->applyColumns($options['columns'], $alias, $language);
        }

        $select->join([$alias => 'item_parent'], $id . ' = ' . $alias. '.parent_id', $columns);

        if (isset($options['link_catname']) && $options['link_catname']) {
            $select->where([$alias . '.catname' => $options['link_catname']]);
        }

        if (isset($options['link_type'])) {
            $this->applyLinkTypeFilter($select, $alias, $options['link_type']);
        }

        return $this->applyFilters($select, array_replace(
            ['language' => $language],
            $options
        ), $alias . '.item_id', $alias, $language);
    }

    private function applyParentFilters(Sql\Select $select, $options, $prefix, $language, string $id): array
    {
        if (! is_array($options)) {
            $options = [
                'id' => $options
            ];
        }

        $alias = $prefix.'ip1';

        $columns = [];

        if (isset($options['columns']) && $options['columns']) {
            $columns = $this->applyColumns($options['columns'], $alias, $language);
        }

        $select->join([$alias => 'item_parent'], $id . ' = ' . $alias . '.item_id', $columns);

        if (isset($options['linked_in_days']) && $options['linked_in_days']) {
            $select->where([
                new Sql\Predicate\Expression(
                    $alias . '.timestamp > DATE_SUB(NOW(), INTERVAL ? DAY)',
                    [$options['linked_in_days']]
                )
            ]);
        }

        if (isset($options['link_catname']) && $options['link_catname']) {
            $select->where([$alias . '.catname' => $options['link_catname']]);
        }

        if (isset($options['link_type'])) {
            $this->applyLinkTypeFilter($select, $alias, $options['link_type']);
        }

        $group = $this->applyFilters($select, array_replace(
            ['language' => $language],
            $options
        ), $alias . '.parent_id', $alias);

        /*if ($group) {
            foreach ($columns as $column) {
                $group[] = $column;
            }
        }*/

        return $group;
    }

    private function applyDescendantFilters(Sql\Select $select, $options, $prefix, $language, string $id): array
    {
        if (! is_array($options)) {
            $options = [
                'id' => $options
            ];
        }

        $alias = $prefix.'ipc1';

        $group = [];
        $columns = [];
        if (isset($options['columns'])) {
            foreach ((array)$options['columns'] as $key => $column) {
                switch ($column) {
                    case 'id':
                        if (is_numeric($key)) {
                            $columns[] = 'item_id';
                            $group[] = 'item_id';
                        } else {
                            $columns[$key] = 'item_id';
                            $group[] = $key;
                        }
                        break;
                    case 'diff':
                        if (is_numeric($key)) {
                            $columns[] = 'diff';
                            $group[] = 'diff';
                        } else {
                            $columns[$key] = 'diff';
                            $group[] = $key;
                        }
                        break;
                    default:
                        throw new Exception("Unexpected column `$column`");
                }
            }
        }

        $select->join([$alias => 'item_parent_cache'], $id . ' = ' . $alias . '.parent_id', $columns)
            ->where([$alias . '.item_id != ' . $alias . '.parent_id']);

        if (isset($options['link_type'])) {
            switch ($options['link_type']) {
                case ItemParent::TYPE_DEFAULT:
                    $select->where([
                        'not ' .$alias . '.sport',
                        'not ' .$alias . '.tuning',
                        'not ' .$alias . '.design'
                    ]);
                    break;
                case ItemParent::TYPE_SPORT:
                    $select->where([$alias . '.sport']);
                    break;
                case ItemParent::TYPE_TUNING:
                    $select->where([$alias . '.tuning']);
                    break;
                case ItemParent::TYPE_DESIGN:
                    $select->where([$alias . '.design']);
                    break;
                default:
                    throw new \Exception("Unexpected link_type");
            }
        }

        $subGroup = $this->applyFilters($select, array_replace(
            ['language' => $language],
            $options
        ), $alias . '.item_id', $alias, $language);
        $group = array_merge($group, $subGroup);

        return $group;
    }

    private function applyLinkTypeFilter(Sql\Select $select, string $alias, $value)
    {
        $column = $alias . '.type';
        if (is_array($value)) {
            if (count($value) <= 0) {
                throw new Exception("Empty link_type value");
            }
            $select->where([new Sql\Predicate\In($column, $value)]);
        } else {
            $select->where([$column => $value]);
        }
    }

    private function applyIdFilter(Sql\Select $select, $value, string $id)
    {
        if (is_array($value)) {
            $value = array_values($value);

            if (count($value) == 1) {
                $this->applyIdFilter($select, $value[0], $id);
                return;
            }

            if (count($value) < 1) {
                $this->applyIdFilter($select, 0, $id);
                return;
            }

            $select->where([new Sql\Predicate\In($id, $value)]);
            return;
        }

        if (! is_scalar($value)) {
            throw new \Exception('`id` must be scalar or array of scalar');
        }

        $select->where([$id => $value]);
    }

    private function applyFilters(Sql\Select $select, array $options, $id, string $prefix): array
    {
        $defaults = [
            'id'                 => null,
            'item_type_id'       => null,
            'descendant'         => null,
            'descendant_or_self' => null,
            'ancestor'           => null,
            'ancestor_or_self'   => null,
            'parent'             => null,
            'child'              => null,
            'has_specs_of_user'  => null,
            'linked_in_days'     => null,
            'catname'            => null,
            'language'           => null,
        ];
        $options = array_replace($defaults, $options);

        $language = isset($options['language']) ? $options['language'] : null;

        $group = [];

        if ($options['id'] !== null) {
            $this->applyIdFilter($select, $options['id'], $id);
        }

        if ($options['item_type_id']) {
            $alias = $prefix.'i1';
            $select->join([$alias => 'item'], $id . ' = ' . $alias. '.id', []);
            if (is_array($options['item_type_id'])) {
                $select->where([new Sql\Predicate\In($alias . '.item_type_id', $options['item_type_id'])]);
            } else {
                $select->where([$alias . '.item_type_id' => $options['item_type_id']]);
            }
        }

        if ($options['descendant']) {
            $group[] = 'item.id';

            $this->applyDescendantFilters($select, $options['descendant'], $prefix, $language, $id);
        }

        if ($options['descendant_or_self']) {
            $group[] = 'item.id';
            $alias = $prefix.'ipc2';

            $columns = [];
            if (is_array($options['descendant_or_self'])) {
                if (isset($options['descendant_or_self']['columns'])) {
                    foreach ((array)$options['descendant_or_self']['columns'] as $key => $column) {
                        switch ($column) {
                            case 'id':
                                if (is_numeric($key)) {
                                    $columns[] = 'item_id';
                                    $group[] = 'item_id';
                                } else {
                                    $columns[$key] = 'item_id';
                                    $group[] = $key;
                                }
                                break;
                            case 'diff':
                                if (is_numeric($key)) {
                                    $columns[] = 'diff';
                                    $group[] = 'diff';
                                } else {
                                    $columns[$key] = 'diff';
                                    $group[] = $key;
                                }
                                break;
                            default:
                                throw new Exception("Unexpected column `$column`");
                        }
                    }
                }
            }

            $select->join([$alias => 'item_parent_cache'], $id . ' = ' . $alias . '.parent_id', $columns);

            if (is_array($options['descendant_or_self'])) {
                $subGroup = $this->applyFilters($select, array_replace(
                    ['language' => $language],
                    $options['descendant_or_self']
                ), $alias . '.item_id', $alias, $language);
                $group = array_merge($group, $subGroup);
            } else {
                $select->where([$alias . '.item_id' => $options['descendant_or_self']]);
            }
        }

        if ($options['ancestor']) {
            $group[] = 'item.id';
            $alias = $prefix.'ipc3';
            $select->join([$alias => 'item_parent_cache'], $id . ' = ' . $alias . '.item_id', [])
                ->where([$alias . '.item_id != ' . $alias . '.parent_id']);

            if (is_array($options['ancestor'])) {
                $subGroup = $this->applyFilters($select, array_replace(
                    ['language' => $language],
                    $options['ancestor']
                ), $alias . '.parent_id', $alias, $language);
                $group = array_merge($group, $subGroup);
            } else {
                $select->where([$alias . '.parent_id' => $options['ancestor']]);
            }
        }

        if ($options['ancestor_or_self']) {
            $group[] = 'item.id';
            $alias = $prefix.'ipc4';
            $select->join([$alias => 'item_parent_cache'], $id . ' = ' . $alias . '.item_id', []);

            if (is_array($options['ancestor_or_self'])) {
                if (isset($options['ancestor_or_self']['max_diff']) && $options['ancestor_or_self']['max_diff']) {
                    $select->where([$alias . '.diff <= ?' => $options['ancestor_or_self']['max_diff']]);
                }

                if (isset($options['ancestor_or_self']['stock_only']) && $options['ancestor_or_self']['stock_only']) {
                    $select->where('not ' . $alias . '.tuning')
                           ->where('not ' . $alias . '.sport');
                }

                $subGroup = $this->applyFilters($select, array_replace(
                    ['language' => $language],
                    $options['ancestor_or_self']
                ), $alias . '.parent_id', $alias, $language);
                $group = array_merge($group, $subGroup);
            } else {
                $select->where([$alias . '.parent_id' => $options['ancestor_or_self']]);
            }
        }

        if ($options['parent']) {
            $subGroup = $this->applyParentFilters($select, $options['parent'], $prefix, $language, $id);
            $group = array_merge($group, $subGroup);
        }

        if ($options['child']) {
            $subGroup = $this->applyChildFilters($select, $options['child'], $prefix, $language, $id);
            $group = array_merge($group, $subGroup);
        }

        if ($options['has_specs_of_user']) {
            $group[] = 'item.id';
            $select->join('attrs_user_values', $id . ' = attrs_user_values.item_id', [])
                ->where(['attrs_user_values.user_id' => $options['has_specs_of_user']]);
        }

        if (isset($options['pictures']) && $options['pictures']) {
            $group[] = 'item.id';

            $this->applyPicturesFilter($select, $id, $options['pictures']);
        }

        return $group;
    }

    private function applyPicturesFilter(Sql\Select $select, $id, array $options)
    {
        $defaults = [
            'user'   => null,
            'status' => null,
            'id'     => null,
            'type'   => null,
        ];
        $options = array_replace($defaults, $options);

        $select->join(['pi1' => 'picture_item'], $id . ' = pi1.item_id', [])
            ->join(['p1' => 'pictures'], 'pi1.picture_id = p1.id', []);

        if ($options['user']) {
            $select->where(['p1.owner_id' => $options['user']]);
        }

        if ($options['status']) {
            $select->where(['p1.status' => $options['status']]);
        }

        if ($options['type']) {
            $select->where(['pi1.type' => $options['type']]);
        }

        if ($options['id']) {
            $select->where(['p1.id' => $options['id']]);
        }
    }

    private function getNameSelect($value, string $valueType, string $language): Sql\Select
    {
        $predicate = new Sql\Predicate\Operator(
            'item_id',
            Sql\Predicate\Operator::OP_EQ,
            $value,
            Sql\ExpressionInterface::TYPE_IDENTIFIER,
            $valueType
        );

        $languages = $this->languagePriority->getList($language);

        $select = new Sql\Select($this->itemLanguageTable->getTable());
        $select->columns(['name'])
            ->where([
                $predicate,
                new Sql\Predicate\Expression('length(item_language.name) > 0')
            ])
            ->order([
                new Sql\Expression(
                    'FIELD(item_language.language' . str_repeat(', ?', count($languages)) . ')',
                    $languages
                )
            ])
            ->limit(1);

        return $select;
    }

    private function getItemParentNameSelect(string $itemParentAlias, string $language): Sql\Select
    {
        $predicate1 = new Sql\Predicate\Operator(
            'item_id',
            Sql\Predicate\Operator::OP_EQ,
            $itemParentAlias . '.item_id',
            Sql\ExpressionInterface::TYPE_IDENTIFIER,
            Sql\ExpressionInterface::TYPE_IDENTIFIER
        );
        $predicate2 = new Sql\Predicate\Operator(
            'parent_id',
            Sql\Predicate\Operator::OP_EQ,
            $itemParentAlias . '.parent_id',
            Sql\ExpressionInterface::TYPE_IDENTIFIER,
            Sql\ExpressionInterface::TYPE_IDENTIFIER
        );

        $languages = $this->languagePriority->getList($language);

        $select = $this->itemParentLanguageTable->getSql()->select();
        $select->columns(['name'])
            ->where([
                $predicate1,
                $predicate2,
                new Sql\Predicate\Expression('length(item_parent_language.name) > 0')
            ])
            ->order([
                new Sql\Expression(
                    'FIELD(item_parent_language.language' . str_repeat(', ?', count($languages)) . ')',
                    $languages
                )
            ])
            ->limit(1);

        return $select;
    }

    public function getSelect(array $options): Sql\Select
    {
        $defaults = [
            'columns'         => null,
            'language'        => null,
            'item_type_id'    => null,
            'item_type_id_exclude' => null,
            'exclude_id'      => null,
            'limit'           => null,
            'order'           => null,
            'created_in_days' => null,
            'engine_id'       => null,
            'dateless'        => null,
            'dateful'         => null,
            'is_group'        => null,
            'is_concept'      => null,
            'is_concept_inherit' => null,
            'no_parents'      => null,
            'catname'         => null,
            'vehicle_type_id' => null,
            'has_logo'        => null,
            'has_begin_year'  => null,
            'has_end_year'    => null,
            'has_begin_month' => null,
            'has_end_month'   => null,
            'position'        => null,
        ];
        $options = array_replace($defaults, $options);

        $select = new Sql\Select($this->itemTable->getTable());

        $language = isset($options['language']) ? $options['language'] : null;

        if ($options['columns']) {
            $columns = [];
            foreach ((array)$options['columns'] as $key => $column) {
                if ($column instanceof Sql\Expression) {
                    $columns[$key] = $column;
                    continue;
                }

                if ($column instanceof Sql\Select) {
                    $columns[$key] = $column;
                    continue;
                }

                switch ($column) {
                    case 'id':
                    case 'catname':
                    case 'is_group':
                    case 'is_concept':
                    case 'item_type_id':
                    case 'full_name':
                    case 'logo_id':
                    case 'position':
                    case 'produced':
                    case 'produced_exactly':
                        $columns[] = $column;
                        break;
                    case 'name':
                        if (! $language) {
                            throw new \Exception("Language is required for `name` select");
                        }

                        $subSelect = $this->languagePriority->getSelectItemName($language, $this->itemTable->getAdapter());

                        $columns = array_merge($columns, [
                            'begin_year', 'end_year', 'today',
                            'begin_model_year', 'end_model_year',
                            'body',
                            /*'name' => $this->getNameSelect(
                                'item.id',
                                Sql\ExpressionInterface::TYPE_IDENTIFIER,
                                $language
                            )*/
                            'name' => new Sql\Expression('(' . $subSelect . ')')
                        ]);

                        $select->join('spec', 'item.spec_id = spec.id', [
                            'spec'      => 'short_name',
                            'spec_full' => 'name',
                        ], $select::JOIN_LEFT);

                        break;
                }
            }

            $select->columns($columns);
        }


        $recursiveOptions = $options;
        unset($recursiveOptions['item_type_id']);
        unset($recursiveOptions['item_type_id_exclude']);
        unset($recursiveOptions['catname']);
        unset($recursiveOptions['columns']);

        $group = $this->applyFilters($select, array_replace(
            ['language' => $language],
            $recursiveOptions
        ), 'item.id', '', $language);

        if ($options['item_type_id']) {
            if (is_array($options['item_type_id'])) {
                $select->where([new Sql\Predicate\In('item.item_type_id', $options['item_type_id'])]);
            } else {
                $select->where(['item.item_type_id' => $options['item_type_id']]);
            }
        }

        if (isset($options['position'])) {
            $select->where(['item.position' => $options['position']]);
        }

        if ($options['has_logo']) {
            $select->where('item.logo_id is not null');
        }

        if ($options['has_begin_year']) {
            $select->where('item.begin_year');
        }

        if ($options['has_end_year']) {
            $select->where('item.end_year');
        }

        if ($options['has_begin_month']) {
            $select->where('item.begin_month');
        }

        if ($options['has_end_month']) {
            $select->where('item.end_month');
        }

        if ($options['item_type_id_exclude']) {
            if (is_array($options['item_type_id_exclude'])) {
                $select->where([new Sql\Predicate\NotIn('item.item_type_id', $options['item_type_id_exclude'])]);
            } else {
                $select->where(['item.item_type_id != ?' => $options['item_type_id_exclude']]);
            }
        }

        if ($options['exclude_id']) {
            $select->where(['item.id != ?' => $options['exclude_id']]);
        }

        if ($options['limit']) {
            $select->limit($options['limit']);
        }

        if ($options['created_in_days']) {
            $select->where([
                new Sql\Predicate\Expression(
                    'item.add_datetime > DATE_SUB(NOW(), INTERVAL ? DAY)',
                    [$options['created_in_days']]
                )
            ]);
        }

        if ($options['engine_id']) {
            $select->where(['item.engine_item_id' => $options['engine_id']]);
        }

        if ($options['dateless']) {
            $select->where([
                'item.begin_year is null',
                'item.begin_model_year is null'
            ]);
        }

        if ($options['dateful']) {
            $select->where([
                '(item.begin_year is not null or item.begin_model_year is not null)'
            ]);
        }

        if (isset($options['is_group'])) {
            if ($options['is_group']) {
                $select->where(['item.is_group']);
            } else {
                $select->where(['not item.is_group']);
            }
        }

        if (isset($options['is_concept'])) {
            if ($options['is_concept']) {
                $select->where(['item.is_concept']);
            } else {
                $select->where(['not item.is_concept']);
            }
        }

        if (isset($options['is_concept_inherit'])) {
            if ($options['is_concept_inherit']) {
                $select->where(['item.is_concept_inherit']);
            } else {
                $select->where(['not item.is_concept_inherit']);
            }
        }

        if ($options['no_parents']) {
            $select
                ->join(['ip3' => 'item_parent'], 'item.id = ip3.item_id', [], $select::JOIN_LEFT)
                ->where(['ip3.parent_id is null']);
        }

        if (isset($options['catname'])) {
            $select->where(['item.catname' => $options['catname']]);
        }

        if ($options['vehicle_type_id']) {
            $group[] = 'item.id';
            $select
                ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', [])
                ->where(['vehicle_vehicle_type.vehicle_type_id' => $options['vehicle_type_id']]);
        }

        if ($options['order']) {
            $select->order($options['order']);
        }

        $group = array_unique($group, SORT_STRING);

        if ($group) {
            $joins = $select->getRawState($select::JOINS);
            foreach ($joins as $join) {
                if ($join['type'] != $select::JOIN_LEFT) {
                    foreach ($join['columns'] as $column) {
                        if (is_array($join['name'])) {
                            $column = key($join['name']) . '.' . $column;
                        } else {
                            $column = $join['name'] . '.' . $column;
                        }
                        $group[] = $column;
                    }
                }
            }

            $select->group($group);
        }

        return $select;
    }

    public function getPaginator(array $options): Paginator\Paginator
    {
        return new Paginator\Paginator(
            new Paginator\Adapter\DbSelect(
                $this->getSelect($options),
                $this->itemTable->getAdapter()
            )
        );
    }

    public function getCount(array $options): int
    {
        return $this->getPaginator($options)->getTotalItemCount();
    }

    public function getCountDistinct(array $options): int
    {
        $select = $this->getSelect($options);

        $select->reset(Sql\Select::LIMIT);
        $select->reset(Sql\Select::OFFSET);
        $select->reset(Sql\Select::ORDER);
        $select->reset(Sql\Select::COLUMNS);
        $select->reset(Sql\Select::GROUP);
        $select->columns(['id']);
        $select->quantifier(Sql\Select::QUANTIFIER_DISTINCT);

        $countSelect = new Sql\Select();

        $countSelect->columns(['count' => new Sql\Expression('COUNT(1)')]);
        $countSelect->from(['original_select' => $select]);

        $statement = $this->itemTable->getSql()->prepareStatementForSqlObject($countSelect);
        $row = $statement->execute()->current();

        return (int) $row['count'];
    }

    public function getCountPairs(array $options): array
    {
        $select = $this->getSelect($options);
        $select->reset($select::COLUMNS);
        $select->reset($select::ORDER);
        $select->reset($select::GROUP);
        $select->columns(['id', 'count' => new Sql\Expression('count(1)')])
            ->group('item.id')
            ->order('count DESC');

        $result = [];
        foreach ($this->itemTable->selectWith($select) as $row) {
            $result[(int)$row['id']] = (int)$row['count'];
        }

        return $result;
    }

    public function getRow(array $options)
    {
        $select = $this->getSelect($options);
        $select->limit(1);

        return $this->itemTable->selectWith($select)->current();
    }

    public function isExists(array $options): bool
    {
        $select = $this->getSelect($options);
        $select->reset($select::COLUMNS);
        $select->reset($select::ORDER);
        $select->reset($select::GROUP);
        $select->columns(['id']);
        $select->limit(1);

        return (bool)$this->itemTable->selectWith($select)->current();
    }

    public function getRows(array $options): array
    {
        $select = $this->getSelect($options);
        $result = [];
        foreach ($this->itemTable->selectWith($select) as $row) {
            $result[] = $row;
        }

        return $result;
    }

    public function getIds(array $options): array
    {
        $select = $this->getSelect($options);
        $select->reset($select::COLUMNS);
        $select->columns(['id']);

        $result = [];
        foreach ($this->itemTable->selectWith($select) as $row) {
            $result[] = (int)$row['id'];
        }

        return $result;
    }

    public function getDesignInfo(TreeRouteStack $url, int $itemId, string $language)
    {
        $brand = $this->getRow([
            'language'     => $language,
            'columns'      => ['catname', 'name'],
            'item_type_id' => Item::BRAND,
            'child'        => [
                'id'         => $itemId,
                'link_type'  => ItemParent::TYPE_DESIGN,
                'columns'    => [
                    'brand_item_catname' => 'link_catname'
                ]
            ]
        ]);

        if ($brand) {
            return [
                'name' => $brand['name'], //TODO: formatter
                'url'  => $url->assemble([
                    'action'        => 'brand-item',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $brand['brand_item_catname']
                ], [
                    'name' => 'catalogue'
                ])
            ];
        }

        $brand = $this->getRow([
            'language'     => $language,
            'columns'      => ['catname', 'name'],
            'item_type_id' => Item::BRAND,
            'child'        => [
                'columns'    => [
                    'brand_item_catname' => 'link_catname'
                ],
                'link_type' => ItemParent::TYPE_DESIGN,
                'descendant' => [
                    'id'        => $itemId,
                    'columns'   => ['diff']
                ]
            ],
            'order'        => 'ip2ipc1.diff ASC'
        ]);

        if ($brand) {
            return [
                'name' => $brand['name'], //TODO: formatter
                'url'  => $url->assemble([
                    'action'        => 'brand-item',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $brand['brand_item_catname']
                ], [
                    'name' => 'catalogue'
                ])
            ];
        }

        $brand = $this->getRow([
            'language'     => $language,
            'columns'      => ['catname', 'name'],
            'item_type_id' => Item::BRAND,
            'child'        => [
                'columns'    => [
                    'brand_item_catname' => 'link_catname'
                ],
                'descendant' => [
                    'link_type' => ItemParent::TYPE_DESIGN,
                    'id'        => $itemId,
                    'columns'   => ['diff']
                ]
            ],
            'order'        => 'ip2ipc1.diff ASC'
        ]);

        if ($brand) {
            return [
                'name' => $brand['name'], //TODO: formatter
                'url'  => $url->assemble([
                    'action'        => 'brand-item',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $brand['brand_item_catname']
                ], [
                    'name' => 'catalogue'
                ])
            ];
        }

        return null;
    }
}
