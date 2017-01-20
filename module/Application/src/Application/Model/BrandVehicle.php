<?php

namespace Application\Model;

use Application\Model\DbTable;

use Autowp\ZFComponents\Filter\FilenameSafe;

use Exception;

use Zend_Db_Expr;

class BrandVehicle
{
    /**
     * @var DbTable\Item
     */
    private $itemTable;

    /**
     * @var DbTable\Item\Language
     */
    private $itemLangTable;

    /**
     * @var DbTable\Item\ParentTable
     */
    private $itemParentTable;

    /**
     * @var DbTable\Item\ParentLanguage
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
            DbTable\Item\Type::CATEGORY => true
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

    public function __construct(array $languages)
    {
        $this->languages = $languages;

        $this->itemTable = new DbTable\Item();
        $this->itemLangTable = new DbTable\Item\Language();
        $this->itemParentTable = new DbTable\Item\ParentTable();
        $this->itemParentLanguageTable = new DbTable\Item\ParentLanguage();
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

    private function getBrandAliases(DbTable\Item\Row $parentRow)
    {
        $aliases = [$parentRow['name']];

        $brandAliasTable = new DbTable\BrandAlias();
        $brandAliasRows = $brandAliasTable->fetchAll([
            'item_id = ?' => $parentRow['id'],
            'length(name) > 0'
        ]);
        foreach ($brandAliasRows as $brandAliasRow) {
            $aliases[] = $brandAliasRow->name;
        }

        $itemLangRows = $this->itemLangTable->fetchAll([
            'item_id = ?' => $parentRow['id'],
            'length(name) > 0'
        ]);
        foreach ($itemLangRows as $itemLangRow) {
            $aliases[] = $itemLangRow->name;
        }

        usort($aliases, function ($a, $b) {
            $la = mb_strlen($a);
            $lb = mb_strlen($b);

            if ($la == $lb) {
                return 0;
            }
            return ($la > $lb) ? -1 : 1;
        });

        return $aliases;
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
        $aliases = $this->getBrandAliases($parentRow);
        
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
                
                $specTable = new DbTable\Spec();
                $specRow = $specTable->find($vehicleRow->spec_id)->current();
                
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

    private function extractCatname(DbTable\Item\Row $brandRow, DbTable\Item\Row $vehicleRow)
    {
        $itemParentLangRow = $this->itemParentLanguageTable->fetchRow([
            'parent_id = ?' => $brandRow['id'],
            'item_id = ?'   => $vehicleRow['id'],
            'length(name) > 0'
        ], new \Zend_Db_Expr('language = "en" desc'));
        if ($itemParentLangRow) {
            $diffName = $itemParentLangRow->name;
        } else {
            $diffName = $this->extractName($brandRow, $vehicleRow, 'en');
        }
        
        $filter = new FilenameSafe();
        $catnameTemplate = $filter->filter($diffName);

        $i = 0;
        do {
            $catname = $catnameTemplate . ($i ? '_' . $i : '');

            $exists = (bool)$this->itemParentTable->fetchRow([
                'parent_id = ?' => $brandRow->id,
                'catname = ?'   => $catname,
                'item_id <> ?'  => $vehicleRow->id
            ]);

            $i++;
        } while ($exists);

        return $catname;
    }

    public function create($parentId, $itemId, array $options = [])
    {
        $parentRow = $this->itemTable->find($parentId)->current();
        $itemRow = $this->itemTable->find($itemId)->current();
        if (! $parentRow || ! $itemRow) {
            return false;
        }

        if (! $parentRow->is_group) {
            throw new Exception("Only groups can have childs");
        }

        if (! isset($this->allowedCombinations[$parentRow->item_type_id][$itemRow->item_type_id])) {
            throw new Exception("That type of parent is not allowed for this type");
        }

        $itemId = (int)$itemRow->id;
        $parentId = (int)$parentRow->id;

        $catname = $this->extractCatname($parentRow, $itemRow);
        if (! $catname) {
            throw new Exception('Failed to create catname');
        }

        $defaults = [
            'type'           => DbTable\Item\ParentTable::TYPE_DEFAULT,
            'catname'        => $catname,
            'manual_catname' => isset($options['catname'])
        ];
        $options = array_replace($defaults, $options);

        if (! isset($options['type'])) {
            throw new Exception("Type cannot be null");
        }

        $parentIds = $this->itemParentTable->collectParentIds($parentId);
        if (in_array($itemId, $parentIds)) {
            throw new Exception('Cycle detected');
        }

        $itemParentRow = $this->itemParentTable->fetchRow([
            'parent_id = ?' => $parentId,
            'item_id = ?'   => $itemId
        ]);

        if ($itemParentRow) {
            return false;
        }

        $itemParentRow = $this->itemParentTable->createRow([
            'parent_id'      => $parentId,
            'item_id'        => $itemId,
            'type'           => $options['type'],
            'catname'        => $options['catname'],
            'manual_catname' => $options['manual_catname'] ? 1 : 0,
            'timestamp'      => new Zend_Db_Expr('now()'),
        ]);
        $itemParentRow->save();

        $values = [];
        foreach ($this->languages as $language) {
            $values[$language] = [
                'name' => $this->extractName($parentRow, $itemRow, $language)
            ];
        }

        $this->setItemParentLanguages($parentId, $itemId, $values, true);

        $cpcTable = new DbTable\Item\ParentCache();
        $cpcTable->rebuildCache($itemRow);

        return true;
    }

    public function remove($parentId, $itemId)
    {
        $parentRow = $this->itemTable->find($parentId)->current();
        $itemRow = $this->itemTable->find($itemId)->current();
        if (! $parentRow || ! $itemRow) {
            return false;
        }

        $itemId = (int)$itemRow->id;
        $parentId = (int)$parentRow->id;

        $row = $this->itemParentTable->fetchRow([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId
        ]);
        if ($row) {
            $row->delete();
        }

        $bvlRows = $this->itemParentLanguageTable->fetchAll([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId
        ]);
        foreach ($bvlRows as $bvlRow) {
            $bvlRow->delete();
        }

        $cpcTable = new DbTable\Item\ParentCache();
        $cpcTable->rebuildCache($itemRow);
    }

    private function setItemParentLanguage($parentId, $itemId, $language, array $values, $forceIsAuto)
    {
        $parentId = (int)$parentId;
        $itemId = (int)$itemId;

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
            'name'    => mb_substr($values['name'], 0, DbTable\Item\ParentLanguage::MAX_NAME),
            'is_auto' => $isAuto ? 1 : 0
        ]);
        $bvlRow->save();
    }

    private function setItemParentLanguages($parentId, $itemId, array $values, $forceIsAuto)
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

    public function setItemParent($parentId, $itemId, array $values, $forceIsAuto)
    {
        $parentId = (int)$parentId;
        $itemId = (int)$itemId;

        $itemParentRow = $this->itemParentTable->fetchRow([
            'parent_id = ?' => $parentId,
            'item_id = ?'   => $itemId
        ]);

        if (! $itemParentRow) {
            return false;
        }

        $newCatname = $values['catname'];

        if ($forceIsAuto) {
            $isAuto = true;
        } else {
            $isAuto = ! $itemParentRow->manual_catname;
            if ($itemParentRow->catname != $newCatname) {
                $isAuto = false;
            }
        }

        if (! $newCatname || $newCatname == '_') {
            $parentRow = $this->itemTable->find($parentId)->current();
            $itemRow = $this->itemTable->find($itemId)->current();
            $newCatname = $this->extractCatname($parentRow, $itemRow);
            $isAuto = true;
        }

        $itemParentRow->setFromArray([
            'catname'        => $newCatname,
            'type'           => $values['type'],
            'manual_catname' => $isAuto ? 0 : 1,
        ]);
        $itemParentRow->save();

        return $this->setItemParentLanguages($parentId, $itemId, $values, false);
    }

    public function refreshAuto($parentId, $itemId)
    {
        $itemId = (int)$itemId;
        $parentId = (int)$parentId;

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
        
        $bvRow = $this->itemParentTable->fetchRow([
            'item_id = ?'   => $itemId,
            'parent_id = ?' => $parentId
        ]);
        
        if (! $bvRow) {
            return false;
        }
        if (! $bvRow->manual_catname) {
            $brandRow = $this->itemTable->fetchRow([
                'id = ?' => (int)$parentId
            ]);
            $vehicleRow = $this->itemTable->find($itemId)->current();
        
            $catname = $this->extractCatname($brandRow, $vehicleRow);
            if (! $catname) {
                return false;
            }
        
            $bvRow->catname = $catname;
            $bvRow->save();
        }
        
        return true;
    }

    public function refreshAutoByVehicle($itemId)
    {
        $brandVehicleRows = $this->itemParentTable->fetchAll([
            'item_id = ?' => (int)$itemId
        ]);

        foreach ($brandVehicleRows as $brandVehicleRow) {
            $this->refreshAuto($brandVehicleRow->parent_id, $itemId);
        }

        return true;
    }

    public function refreshAllAuto()
    {
        $brandVehicleRows = $this->itemParentTable->fetchAll([
            'not manual_catname',
        ], ['parent_id', 'item_id']);

        foreach ($brandVehicleRows as $brandVehicleRow) {
            $this->refreshAuto($brandVehicleRow->parent_id, $brandVehicleRow->item_id);
        }

        return true;
    }

    public function getName($parentId, $itemId, $language)
    {
        $bvlRow = $this->itemParentLanguageTable->fetchRow([
            'parent_id = ?' => (int)$parentId,
            'item_id = ?'   => (int)$itemId,
            'language = ?'  => (string)$language
        ]);

        if (! $bvlRow) {
            return null;
        }

        return $bvlRow->name;
    }
}
