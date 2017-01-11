<?php

namespace Application\Model;

use Application\Model\DbTable;

use Autowp\ZFComponents\Filter\FilenameSafe;

class BrandVehicle
{
    /**
     * @var DbTable\Vehicle
     */
    private $itemTable;
    
    /**
     * @var DbTable\Vehicle\Language
     */
    private $itemLangTable;

    /**
     * @var DbTable\Vehicle\ParentTable
     */
    private $itemParentTable;

    /**
     * @var DbTable\Item\ParentLanguage
     */
    private $itemParentLanguageTable;

    private $languages = ['ru', 'en', 'fr', 'zh'];

    public function __construct(array $languages)
    {
        $this->languages = $languages;

        $this->itemTable = new DbTable\Vehicle();
        $this->itemLangTable = new DbTable\Vehicle\Language();
        $this->itemParentTable = new DbTable\Vehicle\ParentTable();
        $this->itemParentLanguageTable = new DbTable\Item\ParentLanguage();
    }

    public function delete($brandId, $vehicleId)
    {
        $brandId = (int)$brandId;
        $vehicleId = (int)$vehicleId;

        $brandRow = $this->itemTable->fetchRow([
            'id = ?' => (int)$brandId,
            'item_type_id = ?' => DbTable\Item\Type::BRAND
        ]);
        if (! $brandRow) {
            return false;
        }

        $this->itemParentLanguageTable->delete([
            'parent_id = ?' => $brandId,
            'item_id = ?'   => $vehicleId
        ]);

        $this->itemParentTable->delete([
            'parent_id = ?' => $brandId,
            'item_id = ?'   => $vehicleId
        ]);
        
        // TODO: rebuild cache

        $brandRow->refreshPicturesCount();

        return true;
    }

    private function getBrandAliases(DbTable\Vehicle\Row $brandRow)
    {
        $aliases = [$brandRow['name']];

        // TODO: aliases
        /*$brandAliasTable = new DbTable\BrandAlias();
        $brandAliasRows = $brandAliasTable->fetchAll([
            'brand_id = ?' => $brandRow['id']
        ]);
        foreach ($brandAliasRows as $brandAliasRow) {
            $aliases[] = $brandAliasRow->name;
        }*/

        $brandLangRows = $this->itemLangTable->fetchAll([
            'item_id = ?' => $brandRow['id']
        ]);
        foreach ($brandLangRows as $brandLangRow) {
            $aliases[] = $brandLangRow->name;
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

    private function getVehicleName(DbTable\Vehicle\Row $vehicleRow, $language)
    {
        $languageRow = $this->itemLangTable->fetchRow([
            'item_id = ?'  => $vehicleRow->id,
            'language = ?' => $language
        ]);

        return $languageRow ? $languageRow->name : $vehicleRow->name;
    }

    private function extractName(DbTable\Vehicle\Row $brandRow, DbTable\Vehicle\Row $vehicleRow, $language)
    {
        $vehicleName = $this->getVehicleName($vehicleRow, $language);
        $aliases = $this->getBrandAliases($brandRow);

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
            $name = $vehicleName;
        }

        return $name;
    }

    private function extractCatname(DbTable\Vehicle\Row $brandRow, DbTable\Vehicle\Row $vehicleRow)
    {
        $filter = new FilenameSafe();
        $catnameTemplate = $filter->filter($this->extractName($brandRow, $vehicleRow, 'en'));

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

    public function create($brandId, $vehicleId)
    {
        throw new Exception("Deprecated");
        
        $brandRow = $this->itemTable->fetchRow([
            'id = ?'           => (int)$brandId,
            'item_type_id = ?' => DbTable\Item\Type::BRAND
        ]);
        $vehicleRow = $this->itemTable->find($vehicleId)->current();
        if (! $brandRow || ! $vehicleRow) {
            return false;
        }

        $brandVehicleRow = $this->itemParentTable->fetchRow([
            'parent_id = ?' => $brandRow->id,
            'item_id = ?'   => $vehicleRow->id
        ]);

        if ($brandVehicleRow) {
            return false;
        }

        $catname = $this->extractCatname($brandRow, $vehicleRow);
        if (! $catname) {
            return false;
        }

        $brandVehicleRow = $this->itemParentTable->createRow([
            'parent_id' => $brandRow->id,
            'item_id'   => $vehicleRow->id,
            'type'      => DbTable\Vehicle\ParentTable::TYPE_DEFAULT,
            'catname'   => $catname,
            'manual_catname' => 0
        ]);
        $brandVehicleRow->save();

        $values = [];
        foreach ($this->languages as $language) {
            $values[$language] = [
                'name' => $this->extractName($brandRow, $vehicleRow, $language)
            ];
        }

        $this->setBrandVehicleLanguages($brandRow->id, $vehicleRow->id, $values, true);

        $brandRow->refreshPicturesCount();

        return true;
    }

    private function setBrandVehicleLanguage($brandId, $vehicleId, $language, array $values, $forceIsAuto)
    {
        $brandId = (int)$brandId;
        $vehicleId = (int)$vehicleId;

        $bvlRow = $this->itemParentLanguageTable->fetchRow([
            'item_id = ?'   => $vehicleId,
            'parent_id = ?' => $brandId,
            'language = ?'  => $language
        ]);
        if (! $bvlRow) {
            $bvlRow = $this->itemParentLanguageTable->createRow([
                'item_id'   => $vehicleId,
                'parent_id' => $brandId,
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
            $brandRow = $this->itemTable->fetchRow([
                'id = ?'           => (int)$brandId,
                //'item_type_id = ?' => DbTable\Item\Type::BRAND
            ]);
            $vehicleRow = $this->itemTable->find($vehicleId)->current();
            $values['name'] = $this->extractName($brandRow, $vehicleRow, $language);
            $isAuto = true;
        }

        $bvlRow->setFromArray([
            'name'    => mb_substr($values['name'], 0, DbTable\Item\ParentLanguage::MAX_NAME),
            'is_auto' => $isAuto ? 1 : 0
        ]);
        $bvlRow->save();
    }

    private function setBrandVehicleLanguages($brandId, $vehicleId, array $values, $forceIsAuto)
    {
        $success = true;
        foreach ($this->languages as $language) {
            $languageValues = [
                'name' => null
            ];
            if (isset($values[$language])) {
                $languageValues = $values[$language];
            }
            if (! $this->setBrandVehicleLanguage($brandId, $vehicleId, $language, $languageValues, $forceIsAuto)) {
                $success = false;
            }
        }

        return $success;
    }

    public function setBrandVehicle($brandId, $vehicleId, array $values, $forceIsAuto)
    {
        $brandId = (int)$brandId;
        $vehicleId = (int)$vehicleId;

        $brandVehicleRow = $this->itemParentTable->fetchRow([
            'parent_id = ?' => $brandId,
            'item_id = ?'   => $vehicleId
        ]);

        if (! $brandVehicleRow) {
            return false;
        }

        $newCatname = $values['catname'];

        if ($forceIsAuto) {
            $isAuto = true;
        } else {
            $isAuto = $brandVehicleRow->is_auto;
            if ($brandVehicleRow->catname != $newCatname) {
                $isAuto = false;
            }
        }

        if (! $newCatname || $newCatname == '_') {
            $brandRow = $this->itemTable->fetchRow([
                'id = ?'           => (int)$brandId,
                'item_type_id = ?' => DbTable\Item\Type::BRAND
            ]);
            $vehicleRow = $this->itemTable->find($vehicleId)->current();
            $newCatname = $this->extractCatname($brandRow, $vehicleRow);
            $isAuto = true;
        }

        $brandVehicleRow->setFromArray([
            'catname'        => $newCatname,
            'type'           => $values['type'],
            'manual_catname' => $isAuto ? 0 : 1,
        ]);
        $brandVehicleRow->save();

        return $this->setBrandVehicleLanguages($brandId, $vehicleId, $values, false);
    }

    public function refreshAuto($brandId, $vehicleId)
    {
        $vehicleId = (int)$vehicleId;
        $brandId = (int)$brandId;

        $bvRow = $this->itemParentTable->fetchRow([
            'item_id = ?'   => $vehicleId,
            'parent_id = ?' => $brandId
        ]);

        if (! $bvRow) {
            return false;
        }
        if (!$bvRow->manual_catname) {
            $brandRow = $this->itemTable->fetchRow([
                'id = ?'           => (int)$brandId,
                //'item_type_id = ?' => DbTable\Item\Type::BRAND
            ]);
            $vehicleRow = $this->itemTable->find($vehicleId)->current();

            $catname = $this->extractCatname($brandRow, $vehicleRow);
            if (! $catname) {
                return false;
            }

            $bvRow->catname = $catname;
            $bvRow->save();
        }

        $bvlRows = $this->itemParentLanguageTable->fetchAll([
            'item_id = ?'   => $vehicleId,
            'parent_id = ?' => $brandId
        ]);

        $values = [];
        foreach ($bvlRows as $bvlRow) {
            $values[$bvlRow->language] = [
                'name' => $bvlRow->is_auto ? null : $bvlRow->name
            ];
        }

        return $this->setBrandVehicleLanguages($brandId, $vehicleId, $values, false);
    }

    public function refreshAutoByVehicle($vehicleId)
    {
        $brandVehicleRows = $this->itemParentTable->fetchAll([
            'item_id = ?' => (int)$vehicleId
        ]);

        foreach ($brandVehicleRows as $brandVehicleRow) {
            $this->refreshAuto($brandVehicleRow->parent_id, $vehicleId);
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

    public function getName($brandId, $vehicleId, $language)
    {
        $bvlRow = $this->itemParentLanguageTable->fetchRow([
            'parent_id = ?' => (int)$brandId,
            'item_id = ?'   => (int)$vehicleId,
            'language = ?'  => (string)$language
        ]);

        if (! $bvlRow) {
            return null;
        }

        return $bvlRow->name;
    }
}
