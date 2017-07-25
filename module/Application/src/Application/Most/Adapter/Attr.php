<?php

namespace Application\Most\Adapter;

use Exception;

use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

class Attr extends AbstractAdapter
{
    protected $attribute;

    protected $order;

    public function setAttribute($value)
    {
        $this->attribute = (int)$value;
    }

    public function setOrder($value)
    {
        $this->order = $value;
    }

    public function getCars(Zend_Db_Table_Select $select, $language)
    {
        $attribute = $this->attributeTable->select(['id' => (int)$this->attribute])->current();
        if (! $attribute) {
            throw new Exception("Attribute '{$this->attribute}' not found");
        }

        $specService = $this->most->getSpecs();

        $valuesTable = $specService->getValueDataTable($attribute['type_id']);
        $tableName = $valuesTable->info(Zend_Db_Table_Abstract::NAME);

        $select
            ->where($tableName.'.attribute_id = ?', $attribute['id'])
            ->where($tableName.'.value IS NOT NULL')
            ->join($tableName, 'item.id='.$tableName.'.item_id', null)
            ->order($tableName.'.value ' . $this->order);

        $cars = $select->getTable()->fetchAll($select);

        $result = [];
        foreach ($cars as $car) {
            $valueText = $specService->getActualValueText($attribute['id'], $car->id, $language);

            $result[] = [
                'car'       => $car,
                'valueText' => $valueText,
            ];
        }

        return [
            'unit' => $specService->getUnit($attribute['unit_id']),
            'cars' => $result,
        ];
    }
}
