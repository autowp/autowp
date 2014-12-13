<?php

class Project_Most_Adapter_Attr extends Project_Most_Adapter_Abstract
{
    protected $_attribute;

    protected $_itemType;

    protected $_order;

    public function setAttribute($value)
    {
        $this->_attribute = (int)$value;
    }

    public function setItemType($value)
    {
        $this->_itemType = (int)$value;
    }

    public function setOrder($value)
    {
        $this->_order = $value;
    }

    public function getCars(Zend_Db_Table_Select $select)
    {
        $itemTypes = new Attrs_Item_Types();
        $attributes = new Attrs_Attributes();

        $attribute = $attributes->find($this->_attribute)->current();
        if (!$attribute) {
            throw new Exception("Attribute '{$this->_attribute}' not found");
        }

        $itemType = $itemTypes->find($this->_itemType)->current();
        if (!$itemType) {
            throw new Exception("Item type '{$this->_itemType}' not found");
        }

        $valuesTable = $attribute->getValueTable();
        $tableName = $valuesTable->info(Zend_Db_Table_Abstract::NAME);

        $select
            ->where($tableName.'.item_type_id = ?', $itemType->id)
            ->where($tableName.'.attribute_id = ?', $attribute->id)
            ->where($tableName.'.value IS NOT NULL');

        switch ($itemType->id) {
            case 1:
                $select
                    ->join($tableName, 'cars.id='.$tableName.'.item_id', null)
                    ->order($tableName.'.value ' . $this->_order);
                break;

            case 2:
                throw new Exception("Equipes deprecated");
                break;
        }

        //print $select; exit;

        $cars = $select->getTable()->fetchAll($select);

        $result = array();
        foreach ($cars as $car) {

            $valueText = '';
            if ($itemType->id == 1) {
                $valueText = $attribute->getActualValueText($itemType, $car->id);
            } elseif ($itemType->id == 2) {
                $valueText = $attribute->getActualValuesRangeText($itemType, $car->getEquipesIds());
            }

            $result[] = array(
                'car'       =>  $car,
                'valueText' => $valueText,
            );
        }

        return array(
            'unit' => $attribute->findParentAttrs_Units(),
            'cars' => $result,
        );
    }
}