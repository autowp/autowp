<?php

namespace Application\Model;

use ArrayAccess;
use ArrayObject;
use Autowp\ZFComponents\Filter\FilenameSafe;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function array_key_exists;
use function array_keys;
use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;
use function in_array;
use function ltrim;
use function mb_strlen;
use function mb_substr;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_ireplace;
use function trim;

class ItemParent
{
    public const MAX_CATNAME       = 150;
    public const MAX_LANGUAGE_NAME = 255;

    public const
        TYPE_DEFAULT = 0,
        TYPE_TUNING  = 1,
        TYPE_SPORT   = 2,
        TYPE_DESIGN  = 3;

    private TableGateway $itemTable;

    private TableGateway $itemParentTable;

    private TableGateway $itemParentLanguageTable;

    private array $languages;

    private array $catnameBlacklist = ['sport', 'tuning', 'related', 'pictures', 'specifications'];

    private TableGateway $specTable;

    private TableGateway $itemParentCacheTable;

    private ItemAlias $itemAlias;

    private Item $itemModel;

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
        $this->languages               = $languages;
        $this->specTable               = $specTable;
        $this->itemModel               = $itemModel;
        $this->itemTable               = $itemTable;
        $this->itemParentTable         = $itemParentTable;
        $this->itemParentLanguageTable = $itemParentLanguageTable;
        $this->itemParentCacheTable    = $itemParentCacheTable;
        $this->itemAlias               = $itemAlias;
    }

    /**
     * @throws Exception
     */
    public function delete(int $parentId, int $itemId): bool
    {
        $brandRow = currentFromResultSetInterface($this->itemTable->select(['id' => $parentId]));
        if (! $brandRow) {
            return false;
        }

        $this->itemParentLanguageTable->delete([
            'parent_id' => $parentId,
            'item_id'   => $itemId,
        ]);

        $this->itemParentTable->delete([
            'parent_id' => $parentId,
            'item_id'   => $itemId,
        ]);

        return true;
    }

    private function getYearsPrefix(?int $begin, ?int $end): string
    {
        if (! $begin && ! $end) {
            return '';
        }

        $bms = (int) ($begin / 100);
        $ems = (int) ($end / 100);

        if ($end === $begin) {
            return (string) $begin;
        }

        if ($bms === $ems) {
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

    /**
     * @param array|ArrayAccess $parentRow
     * @param array|ArrayAccess $vehicleRow
     * @throws Exception
     */
    private function extractName($parentRow, $vehicleRow, string $language): string
    {
        $langName = $this->itemModel->getName($vehicleRow['id'], $language);

        $vehicleName = $langName ?: $vehicleRow['name'];

        $aliases = $this->itemAlias->getAliases($parentRow['id']);

        $name = $vehicleName;
        foreach ($aliases as $alias) {
            $name = str_ireplace('by The ' . $alias . ' Company', '', $name);
            $name = str_ireplace('by ' . $alias, '', $name);
            $name = str_ireplace('di ' . $alias, '', $name);
            $name = str_ireplace('par ' . $alias, '', $name);
            $name = str_ireplace($alias . '-', '', $name);
            $name = str_ireplace('-' . $alias, '', $name);

            $name = preg_replace('/\b' . preg_quote($alias, '/') . '\b/iu', '', $name);
        }

        $name = trim(preg_replace("|[[:space:]]+|", ' ', $name));
        $name = ltrim($name, '/');
        if (! $name) {
            if ($vehicleRow['body'] && ($vehicleRow['body'] !== $parentRow['body'])) {
                $name = $vehicleRow['body'];
            }
        }

        // TODO: fractions
        if (! $name && $vehicleRow['begin_model_year']) {
            $modelYearsDifferent = $vehicleRow['begin_model_year'] !== $parentRow['begin_model_year']
                || $vehicleRow['end_model_year'] !== $parentRow['end_model_year'];
            if ($modelYearsDifferent) {
                $name = $this->getYearsPrefix($vehicleRow['begin_model_year'], $vehicleRow['end_model_year']);
            }
        }

        if (! $name && $vehicleRow['begin_year']) {
            $yearsDifferent = $vehicleRow['begin_year'] !== $parentRow['begin_year']
                || $vehicleRow['end_year'] !== $parentRow['end_year'];
            if ($yearsDifferent) {
                $name = $this->getYearsPrefix($vehicleRow['begin_year'], $vehicleRow['end_year']);
            }
        }

        if (! $name && $vehicleRow['spec_id']) {
            $specsDifferent = $vehicleRow['spec_id'] !== $parentRow['spec_id'];
            if ($specsDifferent) {
                $specRow = currentFromResultSetInterface(
                    $this->specTable->select(['id' => (int) $vehicleRow['spec_id']])
                );

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

    /**
     * @throws Exception
     */
    private function isAllowedCatname(int $itemId, int $parentId, string $catname): bool
    {
        if (mb_strlen($catname) <= 0) {
            return false;
        }

        if (in_array($catname, $this->catnameBlacklist)) {
            return false;
        }

        return ! currentFromResultSetInterface($this->itemParentTable->select([
            'parent_id'    => $parentId,
            'catname'      => $catname,
            'item_id != ?' => $itemId,
        ]));
    }

    /**
     * @param array|ArrayAccess $brandRow
     * @param array|ArrayAccess $vehicleRow
     * @throws Exception
     */
    private function extractCatname($brandRow, $vehicleRow): string
    {
        $diffName = $this->getNamePreferLanguage($brandRow['id'], $vehicleRow['id'], 'en');
        if (! $diffName) {
            $diffName = $this->extractName($brandRow, $vehicleRow, 'en');
        }

        $filter          = new FilenameSafe();
        $catnameTemplate = $filter->filter($diffName);

        $i = 0;
        do {
            $catname = $catnameTemplate . ($i ? '_' . $i : '');

            $allowed = $this->isAllowedCatname($vehicleRow['id'], $brandRow['id'], $catname);

            $i++;
        } while (! $allowed);

        return $catname;
    }

    /**
     * @throws Exception
     */
    public function setItemParentLanguage(
        int $parentId,
        int $itemId,
        string $language,
        array $values,
        bool $forceIsAuto
    ): void {
        $primaryKey = [
            'item_id'   => $itemId,
            'parent_id' => $parentId,
            'language'  => $language,
        ];

        $bvlRow = currentFromResultSetInterface($this->itemParentLanguageTable->select($primaryKey));

        if ($forceIsAuto) {
            $isAuto = true;
        } else {
            $isAuto = $bvlRow ? $bvlRow['is_auto'] : true;
            $name   = $bvlRow ? $bvlRow['name'] : '';
            if (! array_key_exists('name', $values)) {
                throw new Exception("`name` not provided");
            }
            if ($name !== $values['name']) {
                $isAuto = false;
            }
        }

        if (! $values['name']) {
            $parentRow      = currentFromResultSetInterface($this->itemTable->select(['id' => $parentId]));
            $itemRow        = currentFromResultSetInterface($this->itemTable->select(['id' => $itemId]));
            $values['name'] = $this->extractName($parentRow, $itemRow, $language);
            $isAuto         = true;
        }

        $params = array_replace([
            'name'    => mb_substr($values['name'], 0, self::MAX_LANGUAGE_NAME),
            'is_auto' => $isAuto ? 1 : 0,
        ], $primaryKey);
        /** @var Adapter $adapter */
        $adapter = $this->itemParentLanguageTable->getAdapter();
        $adapter->query('
            INSERT INTO item_parent_language (item_id, parent_id, language, name, is_auto)
            VALUES (:item_id, :parent_id, :language, :name, :is_auto)
            ON DUPLICATE KEY UPDATE name = VALUES(name), is_auto = VALUES(is_auto)
        ', $params);
    }

    /**
     * @throws Exception
     */
    private function setItemParentLanguages(int $parentId, int $itemId, array $values, bool $forceIsAuto): void
    {
        foreach ($this->languages as $language) {
            $languageValues = [
                'name' => null,
            ];
            if (isset($values[$language])) {
                $languageValues = $values[$language];
            }
            $this->setItemParentLanguage($parentId, $itemId, $language, $languageValues, $forceIsAuto);
        }
    }

    /**
     * @throws Exception
     */
    public function refreshAuto(int $parentId, int $itemId): bool
    {
        $bvlRows = $this->itemParentLanguageTable->select([
            'item_id'   => $itemId,
            'parent_id' => $parentId,
        ]);

        $values = [];
        foreach ($bvlRows as $bvlRow) {
            $values[$bvlRow['language']] = [
                'name' => $bvlRow['is_auto'] ? null : $bvlRow['name'],
            ];
        }

        $this->setItemParentLanguages($parentId, $itemId, $values, false);

        $bvRow = currentFromResultSetInterface($this->itemParentTable->select([
            'item_id'   => $itemId,
            'parent_id' => $parentId,
        ]));

        if (! $bvRow) {
            return false;
        }
        if (! $bvRow['manual_catname']) {
            $brandRow   = currentFromResultSetInterface($this->itemTable->select(['id' => $parentId]));
            $vehicleRow = currentFromResultSetInterface($this->itemTable->select(['id' => $itemId]));

            $catname = $this->extractCatname($brandRow, $vehicleRow);
            if (! $catname) {
                return false;
            }

            $this->itemParentTable->update([
                'catname' => $catname,
            ], [
                'item_id = ?'   => $itemId,
                'parent_id = ?' => $parentId,
            ]);
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function refreshAutoByVehicle(int $itemId): bool
    {
        foreach ($this->getParentRows($itemId) as $itemParentRow) {
            $this->refreshAuto($itemParentRow['parent_id'], $itemId);
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function refreshAllAuto(): bool
    {
        $itemParentRows = $this->itemParentTable->select([
            'not manual_catname',
        ]);

        foreach ($itemParentRows as $itemParentRow) {
            $this->refreshAuto($itemParentRow['parent_id'], $itemParentRow['item_id']);
        }

        return true;
    }

    public function getParentRows(int $itemId, bool $stockFirst = false): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->where(['item_id' => $itemId]);

        if ($stockFirst) {
            $select->order([
                new Sql\Expression('type = ? desc', [self::TYPE_DEFAULT]),
            ]);
        }

        $rows = $this->itemParentTable->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @return array|ArrayObject|null
     * @throws Exception
     */
    public function getRow(int $parentId, int $itemId)
    {
        return currentFromResultSetInterface($this->itemParentTable->select([
            'parent_id' => $parentId,
            'item_id'   => $itemId,
        ]));
    }

    /**
     * @return array|ArrayObject|null
     * @throws Exception
     */
    public function getRowByCatname(int $parentId, string $catname)
    {
        return currentFromResultSetInterface($this->itemParentTable->select([
            'parent_id' => $parentId,
            'catname'   => $catname,
        ]));
    }

    /**
     * @throws Exception
     */
    public function getName(int $parentId, int $itemId, string $language): ?string
    {
        $bvlRow = currentFromResultSetInterface($this->itemParentLanguageTable->select([
            'parent_id' => $parentId,
            'item_id'   => $itemId,
            'language'  => $language,
        ]));

        if (! $bvlRow) {
            return null;
        }

        return $bvlRow['name'];
    }

    /**
     * @throws Exception
     */
    public function getNamePreferLanguage(int $parentId, int $itemId, string $language): string
    {
        $select = new Sql\Select($this->itemParentLanguageTable->getTable());
        $select->columns(['name'])
            ->where([
                'item_id = ?'   => $itemId,
                'parent_id = ?' => $parentId,
                'length(name) > 0',
            ])
            ->order(new Sql\Expression('language = ? desc', [$language]));

        $row = currentFromResultSetInterface($this->itemParentLanguageTable->selectWith($select));

        return $row ? $row['name'] : '';
    }

    public function getChildItemsIds(int $parentId): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['item_id'])
            ->where(['parent_id' => $parentId]);

        $result = [];
        foreach ($this->itemParentTable->selectWith($select) as $row) {
            $result[] = (int) $row['item_id'];
        }

        return $result;
    }

    private function collectParentInfo(int $id, int $diff = 1): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['parent_id', 'type'])
            ->where(['item_id' => $id]);

        $rows = $this->itemParentTable->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $parentId          = $row['parent_id'];
            $isTuning          = (int) $row['type'] === self::TYPE_TUNING;
            $isSport           = (int) $row['type'] === self::TYPE_SPORT;
            $isDesign          = (int) $row['type'] === self::TYPE_DESIGN;
            $result[$parentId] = [
                'diff'   => $diff,
                'tuning' => $isTuning,
                'sport'  => $isSport,
                'design' => $isDesign,
            ];

            foreach ($this->collectParentInfo($parentId, $diff + 1) as $pid => $info) {
                if (! isset($result[$pid]) || $info['diff'] < $result[$pid]['diff']) {
                    $result[$pid]           = $info;
                    $result[$pid]['tuning'] = $result[$pid]['tuning'] || $isTuning;
                    $result[$pid]['sport']  = $result[$pid]['sport'] || $isSport;
                    $result[$pid]['design'] = $result[$pid]['design'] || $isDesign;
                }
            }
        }

        return $result;
    }

    public function rebuildCache(int $itemId): int
    {
        $parentInfo          = $this->collectParentInfo($itemId);
        $parentInfo[$itemId] = [
            'diff'   => 0,
            'tuning' => false,
            'sport'  => false,
            'design' => false,
        ];

        $updates = 0;

        /** @var Adapter $adapter */
        $adapter = $this->itemParentCacheTable->getAdapter();
        $stmt    = $adapter->createStatement('
            INSERT INTO item_parent_cache (item_id, parent_id, diff, tuning, sport, design)
            VALUES (:item_id, :parent_id, :diff, :tuning, :sport, :design)
            ON DUPLICATE KEY UPDATE
                diff = VALUES(diff),
                tuning = VALUES(tuning),
                sport = VALUES(sport),
                design = VALUES(design)
        ');

        foreach ($parentInfo as $parentId => $info) {
            $result   = $stmt->execute([
                'item_id'   => $itemId,
                'parent_id' => $parentId,
                'diff'      => $info['diff'],
                'tuning'    => $info['tuning'] ? 1 : 0,
                'sport'     => $info['sport'] ? 1 : 0,
                'design'    => $info['design'] ? 1 : 0,
            ]);
            $updates += $result->getAffectedRows();
        }

        $filter = [
            'item_id = ?' => $itemId,
            new Sql\Predicate\NotIn('parent_id', array_keys($parentInfo)),
        ];

        $updates += $this->itemParentCacheTable->delete($filter);

        $childs = $this->getChildItemsIds($itemId);

        foreach ($childs as $child) {
            $updates += $this->rebuildCache($child);
        }

        return $updates;
    }

    /**
     * @throws Exception
     */
    public function hasChildItems(int $parentId): bool
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['item_id'])
            ->where(['parent_id' => $parentId])
            ->limit(1);

        return (bool) currentFromResultSetInterface($this->itemParentTable->selectWith($select));
    }

    /**
     * @throws Exception
     */
    public function getChildItemsCount(int $parentId): int
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['parent_id' => $parentId]);

        $row = currentFromResultSetInterface($this->itemParentTable->selectWith($select));
        return $row ? (int) $row['count'] : 0;
    }

    /**
     * @throws Exception
     */
    public function getParentItemsCount(int $itemId): int
    {
        $select = new Sql\Select($this->itemParentTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['item_id' => $itemId]);

        $row = currentFromResultSetInterface($this->itemParentTable->selectWith($select));
        return $row ? (int) $row['count'] : 0;
    }

    public function getTable(): TableGateway
    {
        return $this->itemParentTable;
    }

    public function getChildItemLinkTypesCount(int $itemId): array
    {
        $select = new Sql\Select($this->itemParentTable->getTable());

        $select->columns(['type', 'count' => new Sql\Expression('count(1)')])
            ->where(['parent_id' => $itemId])
            ->group('type');

        $result = [];
        foreach ($this->itemParentTable->selectWith($select) as $row) {
            $typeId          = (int) $row['type'];
            $result[$typeId] = (int) $row['count'];
        }

        return $result;
    }
}
