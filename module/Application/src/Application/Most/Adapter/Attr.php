<?php

namespace Application\Most\Adapter;

use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

use Application\Model\DbTable\Attr\Attribute;
use Application\Model\DbTable\Attr\Unit;

use Exception;

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
        $attributes = new Attribute();

        $attribute = $attributes->find($this->attribute)->current();
        if (! $attribute) {
            throw new Exception("Attribute '{$this->attribute}' not found");
        }

        $specService = $this->most->getSpecs();

        $valuesTable = $specService->getValueDataTable($attribute->type_id);
        $tableName = $valuesTable->info(Zend_Db_Table_Abstract::NAME);

        $select
            ->where($tableName.'.attribute_id = ?', $attribute->id)
            ->where($tableName.'.value IS NOT NULL')
            ->join($tableName, 'cars.id='.$tableName.'.item_id', null)
            ->order($tableName.'.value ' . $this->order);

        $cars = $select->getTable()->fetchAll($select);

        $result = [];
        foreach ($cars as $car) {
            $valueText = $specService->getActualValueText($attribute->id, $car->id, $language);

            $result[] = [
                'car'       => $car,
                'valueText' => $valueText,
            ];
        }

        return [
            'unit' => $attribute->findParentRow(Unit::class),
            'cars' => $result,
        ];
    }
}
