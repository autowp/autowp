<?php

namespace Application\Model;

use Exception;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Autowp\ZFComponents\Filter\FilenameSafe;

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
     * @var TableGateway
     */
    private $itemTable;

    /**
     * @var TableGateway
     */
    private $itemParentTable;

    /**
     * @var TableGateway
     */
    private $itemParentLanguageTable;

    private $languages = ['en'];

    private $allowedCombinations = [
        Item::VEHICLE => [
            Item::VEHICLE => true
        ],
        Item::ENGINE => [
            Item::ENGINE => true
        ],
        Item::CATEGORY => [
            Item::VEHICLE  => true,
            Item::CATEGORY => true,
            Item::BRAND    => true
        ],
        Item::TWINS => [
            Item::VEHICLE => true
        ],
        Item::BRAND => [
            Item::BRAND   => true,
            Item::VEHICLE => true,
            Item::ENGINE  => true,
        ],
        Item::FACTORY => [
            Item::VEHICLE => true,
            Item::ENGINE  => true,
        ],
        Item::PERSON => [],
        Item::COPYRIGHT => [],
    ];

    private $catnameBlacklist = ['sport', 'tuning', 'related', 'pictures', 'specifications'];

    /**
     * @var TableGateway
     */
    private $specTable;

    /**
     * @var TableGateway
     */
    private $itemParentCacheTable;

    /**
     * @var ItemAlias
     */
    private $itemAlias;

    /**
     * @var Item
     */
    private $itemModel;

    public function __construct(
        array $languages,
        TableGateway $specTable,
        TableGateway $itemParentTable,
        TableGateway $itemTable,
        TableGateway $itemParentLanguageTable,
        TableGateway $itemParentCacheTable,
        ItemAlias $itemAlias,
        Item $itemModel
    ) {
        $this->languages = $languages;
        $this->specTable = $specTable;
        $this->itemModel = $itemModel;

        $this->itemTable = $itemTable;
        $this->itemParentTable = $itemParentTable;
        $this->itemParentLanguageTable = $itemParentLanguageTable;
        $this->itemParentCacheTable = $itemParentCacheTable;
        $this->itemAlias = $itemAlias;
    }

    public function delete($parentId, $itemId)
    {
        $parentId = (int)$parentId;
        $itemId = (int)$itemId;

        $brandRow = $this->itemTable->select([
            'id' => (int)$parentId
        ])->current();
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

    private function extractName($parentRow, $vehicleRow, $language)
    {
        $langName = $this->itemModel->getName($vehicleRow['id'], $language);

        $vehicleName = $langName ? $langName : $vehicleRow['name'];

        $aliases = $this->itemAlias->getAliases($parentRow['id']);

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
            if ($vehicleRow['body'] && ($vehicleRow['body'] != $parentRow['body'])) {
                $name = $vehicleRow['body'];
            }
        }

        // TODO: fractions
        if (! $name && $vehicleRow['begin_model_year']) {
            $modelYearsDifferent = $vehicleRow['begin_model_year'] != $parentRow['begin_model_year']
                || $vehicleRow['end_model_year'] != $parentRow['end_model_year'];
            if ($modelYearsDifferent) {
                $name = $this->getYearsPrefix($vehicleRow['begin_model_year'], $vehicleRow['end_model_year']);
            }
        }

        if (! $name && $vehicleRow['begin_year']) {
            $yearsDifferent = $vehicleRow['begin_year'] != $parentRow['begin_year']
                || $vehicleRow['end_year'] != $parentRow['end_year'];
            if ($yearsDifferent) {
                $name = $this->getYearsPrefix($vehicleRow['begin_year'], $vehicleRow['end_year']);
            }
        }

        if (! $name && $vehicleRow['spec_id']) {
            $specsDifferent = $vehicleRow['spec_id'] != $parentRow['spec_id'];
            if ($specsDifferent) {
                $specRow = $this->specTable->select(['id' => (int)$vehicleRow['spec_id']])->current();

                if ($specRow) {
                    $name = $specRow['short_name'];
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

    private function extractCatname($brandRow, $vehicleRow)
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

    /**
     * @suppress PhanDeprecatedFunction
     * @param int $parentId
     * @param int $itemId
     * @param array $options
     * @return bool
     * @throws Exception
     */
    public function create(int $parentId, int $itemId, array $options = [])
    {
        $parentRow = $this->itemTable->select(['id' => $parentId])->current();
        $itemRow = $this->itemTable->select(['id' => $itemId])->current();
        if (! $parentRow || ! $itemRow) {
            return false;
        }

        if (! $parentRow['is_group']) {
            throw new Exception("Only groups can have childs");
        }

        if (! $this->isAllowedCombination($itemRow['item_type_id'], $parentRow['item_type_id'])) {
            throw new Exception("That type of parent is not allowed for this type");
        }

        $itemId = (int)$itemRow['id'];
        $parentId = (int)$parentRow['id'];

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
        $oldParentRow = $this->itemTable->select(['id' => $fromParentId])->current();
        $newParentRow = $this->itemTable->select(['id' => $toParentId])->current();
        $itemRow = $this->itemTable->select(['id' => $itemId])->current();
        if (! $oldParentRow || ! $newParentRow || ! $itemRow) {
            return false;
        }

        if ($oldParentRow['id'] == $newParentRow['id']) {
            return false;
        }

        if (! $oldParentRow['is_group']) {
            throw new Exception("Only groups can have childs");
        }

        if (! $newParentRow['is_group']) {
            throw new Exception("Only groups can have childs");
        }

        if (! $this->isAllowedCombination($itemRow['item_type_id'], $newParentRow['item_type_id'])) {
            throw new Exception("That type of parent is not allowed for this type");
        }

        $itemId = (int)$itemRow['id'];

        $parentIds = $this->collectAncestorsIds($newParentRow['id']);
        if (in_array($itemId, $parentIds)) {
            throw new Exception('Cycle detected');
        }

        $primaryKey = [
            'parent_id' => $fromParentId,
            'item_id'   => $itemId
        ];

        $itemParentRow = $this->itemParentTable->select($primaryKey)->current();

        if (! $itemParentRow) {
            return false;
        }

        $this->itemParentTable->update([
            'parent_id' => $toParentId
        ], $primaryKey);

        $this->itemParentLanguageTable->update([
            'parent_id' => $toParentId
        ], $primaryKey);

        $this->rebuildCache($itemRow['id']);

        $this->refreshAuto($toParentId, $itemId);

        return true;
    }

    public function remove(int $parentId, int $itemId)
    {
        $parentRow = $this->itemTable->select(['id' => $parentId])->current();
        $itemRow = $this->itemTable->select(['id' => $itemId])->current();
        if (! $parentRow || ! $itemRow) {
            return;
        }

        $itemId = (int)$itemRow['id'];
        $parentId = (int)$parentRow['id'];

        $this->itemParentTable->delete([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId
        ]);

        $this->itemParentLanguageTable->delete([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId
        ]);

        $this->rebuildCache($itemRow['id']);
    }

    /**
     * @param int $parentId
     * @param int $itemId
     * @param string $language
     * @param array $values
     * @param bool $forceIsAuto
     * @throws Exception
     */
    public function setItemParentLanguage(int $parentId, int $itemId, string $language, array $values, bool $forceIsAuto): void
    {
        $primaryKey = [
            'item_id'   => $itemId,
            'parent_id' => $parentId,
            'language'  => $language
        ];

        $bvlRow = $this->itemParentLanguageTable->select($primaryKey)->current();

        if ($forceIsAuto) {
            $isAuto = true;
        } else {
            $isAuto = $bvlRow ? $bvlRow['is_auto'] : true;
            $name = $bvlRow ? $bvlRow['name'] : '';
            if (! array_key_exists('name', $values)) {
                throw new Exception("`name` not provided");
            }
            if ($name != $values['name']) {
                $isAuto = false;
            }
        }

        if (! $values['name']) {
            $parentRow = $this->itemTable->select(['id' => $parentId])->current();
            $itemRow = $this->itemTable->select(['id' => $itemId])->current();
            $values['name'] = $this->extractName($parentRow, $itemRow, $language);
            $isAuto = true;
        }

        $this->itemParentLanguageTable->getAdapter()->query('
            INSERT INTO item_parent_language (item_id, parent_id, language, name, is_auto) 
            VALUES (:item_id, :parent_id, :language, :name, :is_auto)
            ON DUPLICATE KEY UPDATE name = VALUES(name), is_auto = VALUES(is_auto)
        ', array_replace([
            'name'    => mb_substr($values['name'], 0, self::MAX_LANGUAGE_NAME),
            'is_auto' => $isAuto ? 1 : 0
        ], $primaryKey));
    }

    /**
     * @param int $parentId
     * @param int $itemId
     * @param array $values
     * @param bool $forceIsAuto
     * @throws Exception
     */
    private function setItemParentLanguages(int $parentId, int $itemId, array $values, bool $forceIsAuto): void
    {
        foreach ($this->languages as $language) {
            $languageValues = [
                'name' => null
            ];
            if (isset($values[$language])) {
                $languageValues = $values[$language];
            }
            $this->setItemParentLanguage($parentId, $itemId, $language, $languageValues, $forceIsAuto);
        }
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
                $parentRow = $this->itemTable->select(['id' => $parentId])->current();
                $itemRow = $this->itemTable->select(['id' => $itemId])->current();
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
        $bvlRows = $this->itemParentLanguageTable->select([
            'item_id'   => $itemId,
            'parent_id' => $parentId
        ]);

        $values = [];
        foreach ($bvlRows as $bvlRow) {
            $values[$bvlRow['language']] = [
                'name' => $bvlRow['is_auto'] ? null : $bvlRow['name']
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
            $brandRow = $this->itemTable->select(['id' => (int)$parentId])->current();
            $vehicleRow = $this->itemTable->select(['id' => $itemId])->current();

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
        $itemParentRows = $this->itemParentTable->select([
            'not manual_catname',
        ]);

        foreach ($itemParentRows as $itemParentRow) {
            $this->refreshAuto($itemParentRow['parent_id'], $itemParentRow['item_id']);
        }

        return true;
    }

    /**
     * @suppress PhanDeprecatedFunction
     * @param int $itemId
     * @param bool $stockFirst
     * @return array
     */
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

    public function getParentIds(int $itemId): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['parent_id'])
            ->where(['item_id' => $itemId]);

        $rows = $this->itemParentTable->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $result[] = (int)$row['parent_id'];
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
        $bvlRow = $this->itemParentLanguageTable->select([
            'parent_id' => $parentId,
            'item_id'   => $itemId,
            'language'  => $language
        ])->current();

        if (! $bvlRow) {
            return null;
        }

        return $bvlRow['name'];
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod, PhanPluginMixedKeyNoKey
     * @param int $parentId
     * @param int $itemId
     * @param string $language
     * @return string
     */
    public function getNamePreferLanguage(int $parentId, int $itemId, string $language): string
    {
        $select = new Sql\Select($this->itemParentLanguageTable->getTable());
        $select->columns(['name'])
            ->where([
                'item_id = ?'   => $itemId,
                'parent_id = ?' => $parentId,
                'length(name) > 0'
            ])
            ->order(new Sql\Expression('language = ? desc', [$language]));

        $row = $this->itemParentLanguageTable->selectWith($select)->current();

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
            $primaryKey = [
                'item_id'   => $itemId,
                'parent_id' => $parentId
            ];
            $row = $this->itemParentCacheTable->select($primaryKey)->current();

            if ($row) {
                $set = [];
                if ($row['diff'] != $info['diff']) {
                    $set['diff'] = $info['diff'];
                }

                if ($row['tuning'] xor $info['tuning']) {
                    $set['tuning'] = $info['tuning'] ? 1 : 0;
                }

                if ($row['sport'] xor $info['sport']) {
                    $set['sport'] = $info['sport'] ? 1 : 0;
                }

                if ($row['design'] xor $info['design']) {
                    $set['design'] = $info['design'] ? 1 : 0;
                }

                if ($set) {
                    $updates += $this->itemParentCacheTable->update($set, $primaryKey);
                }
            } else {
                $updates += $this->itemParentCacheTable->insert(array_replace([
                    'diff'      => $info['diff'],
                    'tuning'    => $info['tuning'] ? 1 : 0,
                    'sport'     => $info['sport'] ? 1 : 0,
                    'design'    => $info['design'] ? 1 : 0
                ], $primaryKey));
            }
        }

        $filter = [
            'item_id = ?' => $itemId
        ];
        if ($parentInfo) {
            $filter[] = new Sql\Predicate\NotIn('parent_id', array_keys($parentInfo));
        }

        $updates += $this->itemParentCacheTable->delete($filter);

        $childs = $this->getChildItemsIds($itemId);

        foreach ($childs as $child) {
            $updates += $this->rebuildCache($child);
        }

        return $updates;
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @param int $parentId
     * @return bool
     */
    public function hasChildItems(int $parentId): bool
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['item_id'])
            ->where(['parent_id' => $parentId])
            ->limit(1);

        return (bool)$this->itemParentTable->selectWith($select)->current();
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod
     * @param int $parentId
     * @return int
     */
    public function getChildItemsCount(int $parentId): int
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['parent_id' => $parentId]);

        $row = $this->itemParentTable->selectWith($select)->current();
        return $row ? (int)$row['count'] : 0;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod
     * @param int $itemId
     * @return int
     */
    public function getParentItemsCount(int $itemId): int
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['item_id' => $itemId]);

        $row = $this->itemParentTable->selectWith($select)->current();
        return $row ? (int)$row['count'] : 0;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     * @param array $parentIds
     * @return array
     */
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

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     * @param array $parentIds
     * @param array $typeIds
     * @return array
     */
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

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     * @param int $itemId
     * @return array
     */
    public function getChildItemLinkTypesCount(int $itemId): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());

        $select->columns(['type', 'count' => new Sql\Expression('count(1)')])
            ->where(['parent_id' => $itemId])
            ->group('type');

        $result = [];
        foreach ($this->itemParentTable->selectWith($select) as $row) {
            $typeId = (int)$row['type'];
            $result[$typeId] = (int)$row['count'];
        }
        return $result;
    }
}
