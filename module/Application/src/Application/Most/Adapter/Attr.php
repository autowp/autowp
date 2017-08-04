<?php

namespace Application\Most\Adapter;

use Exception;

use Zend\Db\Sql;

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

    public function getCars(Sql\Select $select, $language)
    {
        $attribute = $this->attributeTable->select(['id' => (int)$this->attribute])->current();
        if (! $attribute) {
            throw new Exception("Attribute '{$this->attribute}' not found");
        }

        $specService = $this->most->getSpecs();

        $valuesTable = $specService->getValueDataTable($attribute['type_id']);
        $tableName = $valuesTable->getTable();

        $select
            ->where([
                $tableName.'.attribute_id' => $attribute['id'],
                $tableName.'.value IS NOT NULL'
            ])
            ->join($tableName, 'item.id = '.$tableName.'.item_id', [])
            ->order($tableName.'.value ' . $this->order)
            ->group(['item.id']);

        $result = [];
        foreach ($this->itemTable->selectWith($select) as $car) {
            $valueText = $specService->getActualValueText($attribute['id'], $car['id'], $language);

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
