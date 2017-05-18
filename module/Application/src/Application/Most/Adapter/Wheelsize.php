<?php

namespace Application\Most\Adapter;

use Application\Model\DbTable;
use Application\WheelSize as WheelsizeObject;

use Zend_Db_Expr;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

class Wheelsize extends AbstractAdapter
{
    protected $attributes;

    protected $order;

    protected $attributesTable;

    public function __construct(array $options)
    {
        parent::__construct($options);

        $this->attributesTable = new DbTable\Attr\Attribute();
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
        $tyrewidthValuesTable = $specService->getValueDataTable($tyrewidth->type_id)
            ->info(Zend_Db_Table_Abstract::NAME);

        $tyreseries = $this->attributesTable->find($wheel['tyreseries'])->current();
        $tyreseriesValuesTable = $specService->getValueDataTable($tyreseries->type_id)
            ->info(Zend_Db_Table_Abstract::NAME);

        $radius     = $this->attributesTable->find($wheel['radius'])->current();
        $radiusValuesTable = $specService->getValueDataTable($radius->type_id)
            ->info(Zend_Db_Table_Abstract::NAME);

        $select
            ->join(['tyrewidth' => $tyrewidthValuesTable], 'item.id = tyrewidth.item_id', null)
            ->where('tyrewidth.attribute_id = ?', $tyrewidth->id)
            ->where('tyrewidth.value > 0')
            ->join(['tyreseries' => $tyreseriesValuesTable], 'item.id = tyreseries.item_id', null)
            ->where('tyreseries.attribute_id = ?', $tyreseries->id)
            ->where('tyreseries.value > 0')
            ->join(['radius' => $radiusValuesTable], 'item.id = radius.item_id', null)
            ->where('radius.attribute_id = ?', $radius->id)
            ->where('radius.value > 0')
            ->group('item.id')
            ->order(new Zend_Db_Expr('2*tyrewidth.value*tyreseries.value/100+radius.value*25.4 ' . $this->order));

        $cars = $select->getTable()->fetchAll($select);

        $result = [];

        foreach ($cars as $car) {
            $result[] = [
                'car'       => $car,
                'valueText' => $this->getWheelSizeText($car),
            ];
        }

        return [
            'unit' => null,
            'cars' => $result,
        ];
    }

    protected function getWheelSizeText($car)
    {
        $text = [];

        $specService = $this->most->getSpecs();

        foreach ($this->attributes as $wheel) {
            $wheelObj = new WheelsizeObject(
                $specService->getActualValue($wheel['tyrewidth'], $car->id),
                $specService->getActualValue($wheel['tyreseries'], $car->id),
                $specService->getActualValue($wheel['radius'], $car->id),
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
