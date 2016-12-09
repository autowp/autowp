<?php

namespace Application\Model;

use Application\Model\DbTable;

use Autowp\ZFComponents\Filter\FilenameSafe;

use Zend_Db_Expr;

class BrandVehicle
{
    /**
     * @var DbTable\Brand
     */
    private $brandTable;

    /**
     * @var DbTable\BrandItem
     */
    private $brandVehicleTable;

    /**
     * @var DbTable\Brand\VehicleLanguage
     */
    private $brandVehicleLanguageTable;

    private $languages = ['ru', 'en', 'fr', 'zh'];

    public function __construct(array $languages)
    {
        $this->languages = $languages;

        $this->brandTable = new DbTable\Brand();
        $this->vehicleTable = new DbTable\Vehicle();
        $this->brandVehicleTable = new DbTable\BrandItem();
        $this->brandVehicleLanguageTable = new DbTable\Brand\VehicleLanguage();
    }

    public function delete($brandId, $vehicleId)
    {
        $brandId = (int)$brandId;
        $vehicleId = (int)$vehicleId;

        $brandRow = $this->brandTable->find($brandId)->current();
        if (! $brandRow) {
            return false;
        }

        $this->brandVehicleLanguageTable->delete([
            'brand_id = ?'   => $brandId,
            'vehicle_id = ?' => $vehicleId
        ]);

        $this->brandVehicleTable->delete([
            'brand_id = ?' => $brandId,
            'car_id = ?'   => $vehicleId
        ]);

        $brandRow->refreshPicturesCount();

        return true;
    }

    private function getBrandAliases(DbTable\BrandRow $brandRow)
    {
        $aliases = [$brandRow['name']];

        $brandAliasTable = new DbTable\BrandAlias();
        $brandAliasRows = $brandAliasTable->fetchAll([
            'brand_id = ?' => $brandRow['id']
        ]);
        foreach ($brandAliasRows as $brandAliasRow) {
            $aliases[] = $brandAliasRow->name;
        }

        $brandLangTable = new DbTable\BrandLanguage();
        $brandLangRows = $brandLangTable->fetchAll([
            'brand_id = ?' => $brandRow['id']
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
        $languageTable = new DbTable\Vehicle\Language;

        $languageRow = $languageTable->fetchRow([
            'car_id = ?'   => $vehicleRow->id,
            'language = ?' => $language
        ]);

        return $languageRow ? $languageRow->name : $vehicleRow->name;
    }

    private function extractName(DbTable\BrandRow $brandRow, DbTable\Vehicle\Row $vehicleRow, $language)
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

    private function extractCatname(DbTable\BrandRow $brandRow, DbTable\Vehicle\Row $vehicleRow)
    {
        $filter = new FilenameSafe();
        $catnameTemplate = $filter->filter($this->extractName($brandRow, $vehicleRow, 'en'));

        $i = 0;
        do {
            $catname = $catnameTemplate . ($i ? '_' . $i : '');

            $exists = (bool)$this->brandVehicleTable->fetchRow([
                'brand_id = ?' => $brandRow->id,
                'catname = ?'  => $catname,
                'car_id <> ?'  => $vehicleRow->id
            ]);

            $i++;
        } while ($exists);

        return $catname;
    }

    public function create($brandId, $vehicleId)
    {
        $brandRow = $this->brandTable->find($brandId)->current();
        $vehicleRow = $this->vehicleTable->find($vehicleId)->current();
        if (! $brandRow || ! $vehicleRow) {
            return false;
        }

        $brandVehicleRow = $this->brandVehicleTable->fetchRow([
            'brand_id = ?' => $brandRow->id,
            'car_id = ?'   => $vehicleRow->id
        ]);

        if ($brandVehicleRow) {
            return false;
        }

        $catname = $this->extractCatname($brandRow, $vehicleRow);
        if (! $catname) {
            return false;
        }

        $brandVehicleRow = $this->brandVehicleTable->createRow([
            'brand_id' => $brandRow->id,
            'car_id'   => $vehicleRow->id,
            'type'     => DbTable\BrandItem::TYPE_DEFAULT,
            'catname'  => $catname,
            'is_auto'  => 1
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

        $bvlRow = $this->brandVehicleLanguageTable->fetchRow([
            'vehicle_id = ?' => $vehicleId,
            'brand_id = ?'   => $brandId,
            'language = ?'   => $language
        ]);
        if (! $bvlRow) {
            $bvlRow = $this->brandVehicleLanguageTable->createRow([
                'vehicle_id' => $vehicleId,
                'brand_id'   => $brandId,
                'language'   => $language
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
            $brandRow = $this->brandTable->find($brandId)->current();
            $vehicleRow = $this->vehicleTable->find($vehicleId)->current();
            $values['name'] = $this->extractName($brandRow, $vehicleRow, $language);
            $isAuto = true;
        }

        $bvlRow->setFromArray([
            'name'    => mb_substr($values['name'], 0, DbTable\Brand\VehicleLanguage::MAX_NAME),
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

        $brandVehicleRow = $this->brandVehicleTable->fetchRow([
            'brand_id = ?' => $brandId,
            'car_id = ?'   => $vehicleId
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
            $brandRow = $this->brandTable->find($brandId)->current();
            $vehicleRow = $this->vehicleTable->find($vehicleId)->current();
            $newCatname = $this->extractCatname($brandRow, $vehicleRow);
            $isAuto = true;
        }

        $brandVehicleRow->setFromArray([
            'catname' => $newCatname,
            'type'    => $values['type'],
            'is_auto' => $isAuto ? 1 : 0,
        ]);
        $brandVehicleRow->save();

        return $this->setBrandVehicleLanguages($brandId, $vehicleId, $values, false);
    }

    public function refreshAuto($brandId, $vehicleId)
    {
        $vehicleId = (int)$vehicleId;
        $brandId = (int)$brandId;

        $bvRow = $this->brandVehicleTable->fetchRow([
            'car_id = ?'   => $vehicleId,
            'brand_id = ?' => $brandId
        ]);

        if (! $bvRow) {
            return false;
        }
        if ($bvRow->is_auto) {
            $brandRow = $this->brandTable->find($brandId)->current();
            $vehicleRow = $this->vehicleTable->find($vehicleId)->current();

            $catname = $this->extractCatname($brandRow, $vehicleRow);
            if (! $catname) {
                return false;
            }

            $bvRow->catname = $catname;
            $bvRow->save();
        }

        $bvlRows = $this->brandVehicleLanguageTable->fetchAll([
            'vehicle_id = ?' => $vehicleId,
            'brand_id = ?'   => $brandId
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
        $brandVehicleRows = $this->brandVehicleTable->fetchAll([
            'car_id = ?' => (int)$vehicleId
        ]);

        foreach ($brandVehicleRows as $brandVehicleRow) {
            $this->refreshAuto($brandVehicleRow->brand_id, $vehicleId);
        }

        return true;
    }

    public function refreshAllAuto()
    {
        $brandVehicleRows = $this->brandVehicleTable->fetchAll([
            'is_auto',
            'brand_id >= 58'
        ], ['brand_id', 'car_id']);

        foreach ($brandVehicleRows as $brandVehicleRow) {
            $this->refreshAuto($brandVehicleRow->brand_id, $brandVehicleRow->car_id);
        }

        return true;
    }

    public function getName($brandId, $vehicleId, $language)
    {
        $bvlRow = $this->brandVehicleLanguageTable->fetchRow([
            'brand_id = ?'   => (int)$brandId,
            'vehicle_id = ?' => (int)$vehicleId,
            'language = ?'   => (string)$language
        ]);

        if (! $bvlRow) {
            return null;
        }

        return $bvlRow->name;
    }
}
