<?php

namespace Application\Most\Adapter;

use Application\Model\DbTable\Attr\ItemType;
use Application\Model\DbTable\Attr\Attribute;
use Application\Model\DbTable\Attr\Unit;
use Application\WheelSize as WheelsizeObject;

use Zend_Db_Expr;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

class Wheelsize extends AbstractAdapter
{
    protected $attributes;

    protected $order;

    protected $attributesTable;

    protected $carItemType;

    public function __construct(array $options)
    {
        parent::__construct($options);

        $this->attributesTable = new Attribute();

        $itemTypes = new ItemType();
        $this->carItemType = $itemTypes->find(1)->current();
    }

    public function setAttributes(array $value)
    {
        /*$defaults = [
            'tyrewidth'  => null,
            'tyreseries' => null,
            'radius'  => null,
            'rimwidth'   => null
        ];*/
        $this->attributes = $value;
    }

    public function setOrder($value)
    {
        $this->order = $value;
    }

    public function getCars(Zend_Db_Table_Select $select, $language)
    {
        $wheel = $this->attributes['rear'];

        $specService = $this->most->getSpecs();

        $tyrewidth  = $this->attributesTable->find($wheel['tyrewidth'])->current();
        $tyrewidthValuesTable = $specService->getValueDataTable($tyrewidth->type_id)->info(Zend_Db_Table_Abstract::NAME);

        $tyreseries = $this->attributesTable->find($wheel['tyreseries'])->current();
        $tyreseriesValuesTable = $specService->getValueDataTable($tyreseries->type_id)->info(Zend_Db_Table_Abstract::NAME);

        $radius     = $this->attributesTable->find($wheel['radius'])->current();
        $radiusValuesTable = $specService->getValueDataTable($radius->type_id)->info(Zend_Db_Table_Abstract::NAME);

        $select
            ->join(['tyrewidth' => $tyrewidthValuesTable], 'cars.id = tyrewidth.item_id', null)
            ->where('tyrewidth.item_type_id = ?', 1)
            ->where('tyrewidth.attribute_id = ?', $tyrewidth->id)
            ->where('tyrewidth.value > 0')
            ->join(['tyreseries' => $tyreseriesValuesTable], 'cars.id = tyreseries.item_id', null)
            ->where('tyreseries.item_type_id = ?', 1)
            ->where('tyreseries.attribute_id = ?', $tyreseries->id)
            ->where('tyreseries.value > 0')
            ->join(['radius' => $radiusValuesTable], 'cars.id = radius.item_id', null)
            ->where('radius.item_type_id = ?', 1)
            ->where('radius.attribute_id = ?', $radius->id)
            ->where('radius.value > 0')
            ->group('cars.id')
            ->order(new Zend_Db_Expr('tyrewidth.value*tyreseries.value/100+radius.value*25.4 ' . $this->order));

        $cars = $select->getTable()->fetchAll($select);

        $result = [];

        foreach ($cars as $car) {
            $result[] = [
                'car'       => $car,
                'valueText' => $this->_getWheelSizeText($car),
            ];
        }

        return [
            'unit' => null,
            'cars' => $result,
        ];
    }

    protected function _getWheelSizeText($car)
    {
        $text = [];

        $specService = $this->most->getSpecs();

        foreach ($this->attributes as $wheel) {
            $wheelObj = new WheelsizeObject(
                $specService->getActualValue($wheel['tyrewidth'], $car->id, 1),
                $specService->getActualValue($wheel['tyreseries'], $car->id, 1),
                $specService->getActualValue($wheel['radius'], $car->id, 1),
                null
            );
            $value = $wheelObj->getTyreName();
            if ($value) {
                $text[$value] = 0;
            }
        }

        return implode(', ', array_keys($text));
    }
}
