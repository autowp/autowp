<?php

namespace Application\Most\Adapter;

use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

use Application\Model\DbTable\Attr;

use Exception;

class Attr extends AbstractAdapter
{
    protected $attribute;

    protected $itemType;

    protected $order;

    public function setAttribute($value)
    {
        $this->attribute = (int)$value;
    }

    public function setItemType($value)
    {
        $this->itemType = (int)$value;
    }

    public function setOrder($value)
    {
        $this->order = $value;
    }

    public function getCars(Zend_Db_Table_Select $select, $language)
    {
        $itemTypes = new Attr\ItemType();
        $attributes = new Attr\Attribute();

        $attribute = $attributes->find($this->attribute)->current();
        if (!$attribute) {
            throw new Exception("Attribute '{$this->attribute}' not found");
        }

        $itemType = $itemTypes->find($this->itemType)->current();
        if (!$itemType) {
            throw new Exception("Item type '{$this->itemType}' not found");
        }

        $specService = $this->most->getSpecs();

        $valuesTable = $specService->getValueDataTable($attribute->type_id);
        $tableName = $valuesTable->info(Zend_Db_Table_Abstract::NAME);

        $select
            ->where($tableName.'.item_type_id = ?', $itemType->id)
            ->where($tableName.'.attribute_id = ?', $attribute->id)
            ->where($tableName.'.value IS NOT NULL')
            ->join($tableName, 'cars.id='.$tableName.'.item_id', null)
            ->order($tableName.'.value ' . $this->order);

        $cars = $select->getTable()->fetchAll($select);

        $result = [];
        foreach ($cars as $car) {

            $valueText = $specService->getActualValueText($attribute->id, $itemType->id, $car->id, $language);

            $result[] = [
                'car'       => $car,
                'valueText' => $valueText,
            ];
        }

        return [
            'unit' => $attribute->findParentRow(Attr\Unit::class),
            'cars' => $result,
        ];
    }
}