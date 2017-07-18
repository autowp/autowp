<?php

namespace Application\Model;

use Exception;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Autowp\ZFComponents\Filter\FilenameSafe;

use Application\Model\DbTable;

use Zend_Db_Expr;
use Zend_Db_Table;

class ItemParent
{
    const MAX_CATNAME = 150;
    const MAX_LANGUAGE_NAME = 255;

    const
        TYPE_DEFAULT = 0,
        TYPE_TUNING = 1,
        TYPE_SPORT = 2,
        TYPE_DESIGN = 3;

    /**
     * @var DbTable\Item
     */
    private $itemTable;

    /**
     * @var DbTable\Item\Language
     */
    private $itemLangTable;

    /**
     * @var TableGateway
     */
    private $itemParentTable;

    /**
     * @var Zend_Db_Table
     */
    private $itemParentLanguageTable;

    private $languages = ['en'];

    private $allowedCombinations = [
        DbTable\Item\Type::VEHICLE => [
            DbTable\Item\Type::VEHICLE => true
        ],
        DbTable\Item\Type::ENGINE => [
            DbTable\Item\Type::ENGINE => true
        ],
        DbTable\Item\Type::CATEGORY => [
            DbTable\Item\Type::VEHICLE  => true,
            DbTable\Item\Type::CATEGORY => true,
            DbTable\Item\Type::BRAND    => true
        ],
        DbTable\Item\Type::TWINS => [
            DbTable\Item\Type::VEHICLE => true
        ],
        DbTable\Item\Type::BRAND => [
            DbTable\Item\Type::BRAND   => true,
            DbTable\Item\Type::VEHICLE => true,
            DbTable\Item\Type::ENGINE  => true,
        ],
        DbTable\Item\Type::FACTORY => [
            DbTable\Item\Type::VEHICLE => true,
            DbTable\Item\Type::ENGINE  => true,
        ]
    ];

    private $catnameBlacklist = ['sport', 'tuning', 'related', 'pictures', 'specifications'];

    /**
     * @var TableGateway
     */
    private $specTable;

    /**
     * @var Zend_Db_Table
     */
    private $itemParentCacheTable;

    /**
     * @var ItemAlias
     */
    private $itemAlias;

    public function __construct(
        array $languages,
        TableGateway $specTable,
        TableGateway $itemParentTable,
        \Zend_Db_Adapter_Abstract $zf1db,
        ItemAlias $itemAlias
    ) {
        $this->languages = $languages;
        $this->specTable = $specTable;

        $this->itemTable = new DbTable\Item([
            'db' => $zf1db
        ]);
        $this->itemLangTable = new DbTable\Item\Language([
            'db' => $zf1db
        ]);
        $this->itemParentTable = $itemParentTable;
        $this->itemParentLanguageTable = new Zend_Db_Table([
            'db'      => $zf1db,
            'name'    => 'item_parent_language',
            'primary' => ['item_id', 'parent_id', 'language']
        ]);
        $this->itemParentCacheTable = new Zend_Db_Table([
            'db'      => $zf1db,
            'name'    => 'item_parent_cache',
            'primary' => ['item_id', 'parent_id']
        ]);
        $this->itemAlias = $itemAlias;
    }

    public function delete($parentId, $itemId)
    {
        $parentId = (int)$parentId;
        $itemId = (int)$itemId;

        $brandRow = $this->itemTable->fetchRow([
            'id = ?' => (int)$parentId
        ]);
        if (! $brandRow) {
            return false;
        }

        $this->itemParentLanguageTable->delete([
            'parent_id = ?' => $parentId,
            'item_id = ?'   => $itemId
        ]);

        $this->itemParentTable->delete([
            'parent_id = ?' => $parentId,
            'item_id = ?'   => $itemId
        ]);

        return true;
    }

    private function getVehicleName(DbTable\Item\Row $itemRow, $language)
    {
        $languageRow = $this->itemLangTable->fetchRow([
            'item_id = ?'  => $itemRow->id,
            'language = ?' => $language
        ]);

        return $languageRow && $languageRow->name ? $languageRow->name : $itemRow->name;
    }

    private function getYearsPrefix($begin, $end)
    {
        $bms = (int)($begin / 100);
        $ems = (int)($end / 100);

        if ($end == $begin) {
            return $begin;
        }

        if ($bms == $ems) {
            return $begin . '–' . sprintf('%02d', $end % 100);
        }

        if (! $begin) {
            return 'xx–' . $end;
        }

        if ($end) {
            return $begin . '–' . $end;
        }

        return $begin . '–xx';
    }

    private function extractName(DbTable\Item\Row $parentRow, DbTable\Item\Row $vehicleRow, $language)
    {
        $vehicleName = $this->getVehicleName($vehicleRow, $language);
        $aliases = $this->itemAlias->getAliases($parentRow['id'], $parentRow['name']);

        $name = $vehicleName;
        foreach ($aliases as $alias) {
            $name = str_ireplace('by The ' . $alias . ' Company', '', $name);
            $name = str_ireplace('by '.$alias, '', $name);
            $name = str_ireplace('di '.$alias, '', $name);
            $name = str_ireplace('par '.$alias, '', $name);
            $name = str_ireplace($alias.'-', '', $name);
            $name = str_ireplace('-'.$alias, '', $name);

            $name = preg_replace('/\b'.preg_quote($alias, '/').'\b/iu', '', $name);
        }

        $name = trim(preg_replace("|[[:space:]]+|", ' ', $name));
        $name = ltrim($name, '/');
        if (! $name) {
            if ($vehicleRow->body && ($vehicleRow->body != $parentRow->body)) {
                $name = $vehicleRow->body;
            }
        }

        if (! $name && $vehicleRow->begin_model_year) {
            $modelYearsDifferent = $vehicleRow->begin_model_year != $parentRow->begin_model_year
                                || $vehicleRow->end_model_year != $parentRow->end_model_year;
            if ($modelYearsDifferent) {
                $name = $this->getYearsPrefix($vehicleRow->begin_model_year, $vehicleRow->end_model_year);
            }
        }

        if (! $name && $vehicleRow->begin_year) {
            $yearsDifferent = $vehicleRow->begin_year != $parentRow->begin_year
                           || $vehicleRow->end_year != $parentRow->end_year;
            if ($yearsDifferent) {
                $name = $this->getYearsPrefix($vehicleRow->begin_year, $vehicleRow->end_year);
            }
        }

        if (! $name && $vehicleRow->spec_id) {
            $specsDifferent = $vehicleRow->spec_id != $parentRow->spec_id;
            if ($specsDifferent) {
                $specRow = $this->specTable->select(['id' => (int)$vehicleRow->spec_id])->current();

                if ($specRow) {
                    $name = $specRow->short_name;
                }
            }
        }

        if (! $name) {
            $name = $vehicleName;
        }

        return $name;
    }

    private function isAllowedCatname(int $itemId, int $parentId, string $catname): bool
    {
        if (mb_strlen($catname) <= 0) {
            return false;
        }

        if (in_array($catname, $this->catnameBlacklist)) {
            return false;
        }

        return ! $this->itemParentTable->select([
            'parent_id'    => $parentId,
            'catname'      => $catname,
            'item_id != ?' => $itemId
        ])->current();
    }

    private function extractCatname(DbTable\Item\Row $brandRow, DbTable\Item\Row $vehicleRow)
    {
        $diffName = $this->getNamePreferLanguage($brandRow['id'], $vehicleRow['id'], 'en');
        if (! $diffName) {
            $diffName = $this->extractName($brandRow, $vehicleRow, 'en');
        }

        $filter = new FilenameSafe();
        $catnameTemplate = $filter->filter($diffName);

        $i = 0;
        do {
            $catname = $catnameTemplate . ($i ? '_' . $i : '');

            $allowed = $this->isAllowedCatname($vehicleRow['id'], $brandRow['id'], $catname);

            $i++;
        } while (! $allowed);

        return $catname;
    }

    public function isAllowedCombination($itemTypeId, $parentItemTypeId)
    {
        return isset($this->allowedCombinations[$parentItemTypeId][$itemTypeId]);
    }

    public function create(int $parentId, int $itemId, array $options = [])
    {
        $parentRow = $this->itemTable->find($parentId)->current();
        $itemRow = $this->itemTable->find($itemId)->current();
        if (! $parentRow || ! $itemRow) {
            return false;
        }

        if (! $parentRow->is_group) {
            throw new Exception("Only groups can have childs");
        }

        if (! $this->isAllowedCombination($itemRow->item_type_id, $parentRow->item_type_id)) {
            throw new Exception("That type of parent is not allowed for this type");
        }

        $itemId = (int)$itemRow->id;
        $parentId = (int)$parentRow->id;

        if (isset($options['catname'])) {
            $allowed = $this->isAllowedCatname($itemId, $parentId, $options['catname']);
            if (! $allowed) {
                unset($options['catname']);
            }
        }

        if (array_key_exists('type', $options) && $options['type'] === null) {
            unset($options['type']);
        }

        $defaults = [
            'type'           => self::TYPE_DEFAULT,
            'catname'        => null,
            'manual_catname' => isset($options['catname'])
        ];
        $options = array_replace($defaults, $options);

        if (! isset($options['catname']) || ! $options['catname'] || $options['catname'] == '_') {
            $catname = $this->extractCatname($parentRow, $itemRow);
            if (! $catname) {
                throw new Exception('Failed to create catname');
            }

            $options['catname'] = $catname;
        }

        if (! isset($options['type'])) {
            throw new Exception("Type cannot be null");
        }

        $parentIds = $this->collectAncestorsIds($parentId);
        if (in_array($itemId, $parentIds)) {
            throw new Exception('Cycle detected');
        }

        $itemParentRow = $this->itemParentTable->select([
            'parent_id' => $parentId,
            'item_id'   => $itemId
        ])->current();

        if ($itemParentRow) {
            return false;
        }

        $this->itemParentTable->insert([
            'parent_id'      => $parentId,
            'item_id'        => $itemId,
            'type'           => $options['type'],
            'catname'        => $options['catname'],
            'manual_catname' => $options['manual_catname'] ? 1 : 0,
            'timestamp'      => new Sql\Expression('now()'),
        ]);

        $values = [];
        foreach ($this->languages as $language) {
            $values[$language] = [
                'name' => $this->extractName($parentRow, $itemRow, $language)
            ];
        }

        $this->setItemParentLanguages($parentId, $itemId, $values, true);

        $this->rebuildCache($itemId);

        return true;
    }

    public function move(int $itemId, int $fromParentId, int $toParentId)
    {
        $oldParentRow = $this->itemTable->find($fromParentId)->current();
        $newParentRow = $this->itemTable->find($toParentId)->current();
        $itemRow = $this->itemTable->find($itemId)->current();
        if (! $oldParentRow || ! $newParentRow || ! $itemRow) {
            return false;
        }

        if ($oldParentRow->id == $newParentRow->id) {
            return false;
        }

        if (! $oldParentRow->is_group) {
            throw new Exception("Only groups can have childs");
        }

        if (! $newParentRow->is_group) {
            throw new Exception("Only groups can have childs");
        }

        if (! $this->isAllowedCombination($itemRow->item_type_id, $newParentRow->item_type_id)) {
            throw new Exception("That type of parent is not allowed for this type");
        }

        $itemId = (int)$itemRow->id;

        $parentIds = $this->collectAncestorsIds($newParentRow->id);
        if (in_array($itemId, $parentIds)) {
            throw new Exception('Cycle detected');
        }

        $itemParentRow = $this->itemParentTable->select([
            'parent_id' => $fromParentId,
            'item_id'   => $itemId
        ])->current();

        if (! $itemParentRow) {
            return false;
        }

        $this->itemParentTable->update([
            'parent_id' => $toParentId
        ], [
            'parent_id = ?' => $fromParentId,
            'item_id = ?'   => $itemId
        ]);

        $bvlRows = $this->itemParentLanguageTable->fetchAll([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $fromParentId
        ]);
        foreach ($bvlRows as $bvlRow) {
            $bvlRow->setFromArray([
                'parent_id' => $toParentId
            ]);
            $bvlRow->save();
        }

        $this->rebuildCache($itemRow['id']);

        $this->refreshAuto($toParentId, $itemId);

        return true;
    }

    public function remove(int $parentId, int $itemId)
    {
        $parentRow = $this->itemTable->find($parentId)->current();
        $itemRow = $this->itemTable->find($itemId)->current();
        if (! $parentRow || ! $itemRow) {
            return false;
        }

        $itemId = (int)$itemRow->id;
        $parentId = (int)$parentRow->id;

        $this->itemParentTable->delete([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId
        ]);

        $bvlRows = $this->itemParentLanguageTable->fetchAll([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId
        ]);
        foreach ($bvlRows as $bvlRow) {
            $bvlRow->delete();
        }

        $this->rebuildCache($itemRow['id']);
    }

    public function setItemParentLanguage(int $parentId, int $itemId, string $language, array $values, $forceIsAuto)
    {
        $bvlRow = $this->itemParentLanguageTable->fetchRow([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId,
            'language = ?'  => $language
        ]);
        if (! $bvlRow) {
            $bvlRow = $this->itemParentLanguageTable->createRow([
                'item_id'   => $itemId,
                'parent_id' => $parentId,
                'language'  => $language
            ]);
        }

        $newName = $values['name'];

        if ($forceIsAuto) {
            $isAuto = true;
        } else {
            $isAuto = $bvlRow->is_auto;
            if ($bvlRow->name != $newName) {
                $isAuto = false;
            }
        }

        if (! $values['name']) {
            $parentRow = $this->itemTable->find($parentId)->current();
            $itemRow = $this->itemTable->find($itemId)->current();
            $values['name'] = $this->extractName($parentRow, $itemRow, $language);
            $isAuto = true;
        }

        $bvlRow->setFromArray([
            'name'    => mb_substr($values['name'], 0, self::MAX_LANGUAGE_NAME),
            'is_auto' => $isAuto ? 1 : 0
        ]);
        $bvlRow->save();
    }

    private function setItemParentLanguages(int $parentId, int $itemId, array $values, $forceIsAuto)
    {
        $success = true;
        foreach ($this->languages as $language) {
            $languageValues = [
                'name' => null
            ];
            if (isset($values[$language])) {
                $languageValues = $values[$language];
            }
            if (! $this->setItemParentLanguage($parentId, $itemId, $language, $languageValues, $forceIsAuto)) {
                $success = false;
            }
        }

        return $success;
    }

    public function setItemParent(int $parentId, int $itemId, array $values, $forceIsAuto)
    {
        $itemParentRow = $this->itemParentTable->select([
            'parent_id' => $parentId,
            'item_id'   => $itemId
        ])->current();

        if (! $itemParentRow) {
            return false;
        }

        $set = [];

        if (array_key_exists('type', $values)) {
            $set['type'] = $values['type'];
        }

        if (array_key_exists('catname', $values)) {
            $newCatname = $values['catname'];

            if ($forceIsAuto) {
                $isAuto = true;
            } else {
                $isAuto = ! $itemParentRow['manual_catname'];
                if ($itemParentRow['catname'] != $newCatname) {
                    $isAuto = false;
                }
            }

            if (! $newCatname || $newCatname == '_' || in_array($newCatname, $this->catnameBlacklist)) {
                $parentRow = $this->itemTable->find($parentId)->current();
                $itemRow = $this->itemTable->find($itemId)->current();
                $newCatname = $this->extractCatname($parentRow, $itemRow);
                $isAuto = true;
            }

            $set = array_replace($set, [
                'catname'        => $newCatname,
                'manual_catname' => $isAuto ? 0 : 1,
            ]);
        }

        if ($set) {
            $this->itemParentTable->update($set, [
                'parent_id = ?' => $parentId,
                'item_id = ?'   => $itemId
            ]);
        }

        return true;
    }

    public function refreshAuto(int $parentId, int $itemId)
    {
        $bvlRows = $this->itemParentLanguageTable->fetchAll([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId
        ]);

        $values = [];
        foreach ($bvlRows as $bvlRow) {
            $values[$bvlRow->language] = [
                'name' => $bvlRow->is_auto ? null : $bvlRow->name
            ];
        }

        $this->setItemParentLanguages($parentId, $itemId, $values, false);

        $bvRow = $this->itemParentTable->select([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId
        ])->current();

        if (! $bvRow) {
            return false;
        }
        if (! $bvRow['manual_catname']) {
            $brandRow = $this->itemTable->fetchRow([
                'id = ?' => (int)$parentId
            ]);
            $vehicleRow = $this->itemTable->find($itemId)->current();

            $catname = $this->extractCatname($brandRow, $vehicleRow);
            if (! $catname) {
                return false;
            }

            $this->itemParentTable->update([
                'catname' => $catname
            ], [
                'item_id = ?'   => $itemId,
                'parent_id = ?' => $parentId
            ]);
        }

        return true;
    }

    public function refreshAutoByVehicle(int $itemId)
    {
        foreach ($this->getParentRows($itemId) as $itemParentRow) {
            $this->refreshAuto($itemParentRow['parent_id'], $itemId);
        }

        return true;
    }

    public function refreshAllAuto()
    {
        $itemParentRows = $this->itemParentTable->fetchAll([
            'not manual_catname',
        ], ['parent_id', 'item_id']);

        foreach ($itemParentRows as $itemParentRow) {
            $this->refreshAuto($itemParentRow->parent_id, $itemParentRow->item_id);
        }

        return true;
    }

    public function getParentRows(int $itemId, bool $stockFirst = false): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->where(['item_id' => $itemId]);

        if ($stockFirst) {
            $select->order([
                new Sql\Expression('type = ? desc', [self::TYPE_DEFAULT])
            ]);
        }

        $rows = $this->itemParentTable->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row;
        }

        return $result;
    }

    public function getRow(int $parentId, int $itemId)
    {
        return $this->itemParentTable->select([
            'parent_id = ?' => $parentId,
            'item_id = ?'   => $itemId
        ])->current();
    }

    public function getRowByCatname(int $parentId, string $catname)
    {
        return $this->itemParentTable->select([
            'parent_id' => $parentId,
            'catname'   => $catname
        ])->current();
    }

    public function getName(int $parentId, int $itemId, string $language)
    {
        $bvlRow = $this->itemParentLanguageTable->fetchRow([
            'parent_id = ?' => $parentId,
            'item_id = ?'   => $itemId,
            'language = ?'  => $language
        ]);

        if (! $bvlRow) {
            return null;
        }

        return $bvlRow->name;
    }

    public function getNamePreferLanguage(int $parentId, int $itemId, string $language): string
    {
        $db = $this->itemParentLanguageTable->getAdapter();
        $langSortExpr = new Zend_Db_Expr(
            $db->quoteInto('language = ? desc', $language)
        );
        $row = $this->itemParentLanguageTable->fetchRow([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId,
            'length(name) > 0'
        ], $langSortExpr);

        return $row ? $row['name'] : '';
    }

    public function getChildItemsIds(int $parentId): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['item_id'])
            ->where(['parent_id' => $parentId]);

        $result = [];
        foreach ($this->itemParentTable->selectWith($select) as $row) {
            $result[] = (int)$row['item_id'];
        }

        return $result;
    }

    private function collectAncestorsIds(int $id): array
    {
        $cpTableName = $this->itemParentTable->getTable();

        $toCheck = [$id];
        $ids = [];

        while (count($toCheck) > 0) {
            $ids = array_merge($ids, $toCheck);

            $select = new Sql\Select($cpTableName);
            $select->columns(['parent_id'])
                ->where([new Sql\Predicate\In('item_id', $toCheck)]);

            $toCheck = [];
            foreach ($this->itemParentTable->selectWith($select) as $row) {
                $toCheck [] = (int)$row['parent_id'];
            }
        }

        return array_unique($ids);
    }

    private function collectParentInfo(int $id, int $diff = 1): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['parent_id', 'type'])
            ->where(['item_id' => $id]);

        $rows = $this->itemParentTable->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'];
            $isTuning = $row['type'] == self::TYPE_TUNING;
            $isSport  = $row['type'] == self::TYPE_SPORT;
            $isDesign = $row['type'] == self::TYPE_DESIGN;
            $result[$parentId] = [
                'diff'   => $diff,
                'tuning' => $isTuning,
                'sport'  => $isSport,
                'design' => $isDesign
            ];

            foreach ($this->collectParentInfo($parentId, $diff + 1) as $pid => $info) {
                if (! isset($result[$pid]) || $info['diff'] < $result[$pid]['diff']) {
                    $result[$pid] = $info;
                    $result[$pid]['tuning'] = $result[$pid]['tuning'] || $isTuning;
                    $result[$pid]['sport']  = $result[$pid]['sport']  || $isSport;
                    $result[$pid]['design'] = $result[$pid]['design'] || $isDesign;
                }
            }
        }

        return $result;
    }

    public function rebuildCache(int $itemId)
    {
        $parentInfo = $this->collectParentInfo($itemId);
        $parentInfo[$itemId] = [
            'diff'   => 0,
            'tuning' => false,
            'sport'  => false,
            'design' => false
        ];

        $updates = 0;

        foreach ($parentInfo as $parentId => $info) {
            $row = $this->itemParentCacheTable->fetchRow([
                'item_id = ?'   => $itemId,
                'parent_id = ?' => $parentId
            ]);
            if (! $row) {
                $row = $this->itemParentCacheTable->createRow([
                    'item_id'   => $itemId,
                    'parent_id' => $parentId,
                    'diff'      => $info['diff'],
                    'tuning'    => $info['tuning'] ? 1 : 0,
                    'sport'     => $info['sport'] ? 1 : 0,
                    'design'    => $info['design'] ? 1 : 0
                ]);
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

        $filter = [
            'item_id = ?' => $itemId
        ];
        if ($parentInfo) {
            $filter['parent_id not in (?)'] = array_keys($parentInfo);
        }

        $this->itemParentCacheTable->delete($filter);

        $childs = $this->getChildItemsIds($itemId);

        foreach ($childs as $child) {
            $this->rebuildCache($child);
        }

        return $updates;
    }

    public function hasChildItems(int $parentId): bool
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['item_id'])
            ->where(['parent_id' => $parentId])
            ->limit(1);

        return (bool)$this->itemParentTable->selectWith($select)->current();
    }

    public function getChildItemsCount(int $parentId): int
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['parent_id' => $parentId]);

        $row = $this->itemParentTable->selectWith($select)->current();
        return $row ? (int)$row['count'] : 0;
    }

    public function getParentItemsCount(int $itemId): int
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['item_id' => $itemId]);

        $row = $this->itemParentTable->selectWith($select)->current();
        return $row ? (int)$row['count'] : 0;
    }

    public function getChildItemsCountArray(array $parentIds): array
    {
        if (count($parentIds) <= 0) {
            return [];
        }

        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['parent_id', 'count' => new Sql\Expression('count(1)')])
            ->where([new Sql\Predicate\In('parent_id', $parentIds)])
            ->group('parent_id');

        $result = [];
        foreach ($this->itemParentTable->selectWith($select) as $row) {
            $result[(int)$row['parent_id']] = (int)$row['count'];
        }

        return $result;
    }

    public function getChildItemsCountArrayByTypes(array $parentIds, array $typeIds): array
    {
        if (count($parentIds) <= 0) {
            return [];
        }

        if (count($typeIds) <= 0) {
            return [];
        }

        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['parent_id', 'type', 'count' => new Sql\Expression('count(1)')])
            ->where([
                new Sql\Predicate\In('parent_id', $parentIds),
                new Sql\Predicate\In('type', $typeIds)
            ])
            ->group(['parent_id', 'type']);

        $rows = $this->itemParentTable->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $itemId = (int)$row['parent_id'];
            $typeId = (int)$row['type'];
            if (! isset($result[$itemId])) {
                $result[$itemId] = [];
            }
            $result[$itemId][$typeId] = (int)$row['count'];
        }

        return $result;
    }

    /**
     * @return TableGateway
     */
    public function getTable()
    {
        return $this->itemParentTable;
    }
}
