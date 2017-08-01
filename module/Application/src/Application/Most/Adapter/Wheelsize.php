<?php

namespace Application\Most\Adapter;

use Zend\Db\Sql;

use Application\WheelSize as WheelsizeObject;

class Wheelsize extends AbstractAdapter
{
    protected $attributes;

    protected $order;

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

    public function getCars(Sql\Select $select, $language)
    {
        $wheel = $this->attributes['rear'];

        $specService = $this->most->getSpecs();

        $tyrewidth  = $this->attributeTable->select(['id' => $wheel['tyrewidth']])->current();
        $tyrewidthValuesTable = $specService->getValueDataTable($tyrewidth['type_id'])->getTable();

        $tyreseries = $this->attributeTable->select(['id' => $wheel['tyreseries']])->current();
        $tyreseriesValuesTable = $specService->getValueDataTable($tyreseries['type_id'])->getTable();

        $radius     = $this->attributeTable->select(['id' => $wheel['radius']])->current();
        $radiusValuesTable = $specService->getValueDataTable($radius['type_id'])->getTable();

        $select
            ->join(['tyrewidth' => $tyrewidthValuesTable], 'item.id = tyrewidth.item_id', [])
            ->join(['tyreseries' => $tyreseriesValuesTable], 'item.id = tyreseries.item_id', [])
            ->join(['radius' => $radiusValuesTable], 'item.id = radius.item_id', [])
            ->where([
                'tyrewidth.attribute_id' => $tyrewidth['id'],
                'tyrewidth.value > 0',
                'tyreseries.attribute_id' => $tyreseries['id'],
                'tyreseries.value > 0',
                'radius.attribute_id' => $radius['id'],
                'radius.value > 0'
            ])
            ->group('item.id')
            ->order(new Sql\Expression('2*tyrewidth.value*tyreseries.value/100+radius.value*25.4 ' . $this->order));

        $cars = $this->itemTable->selectWith($select);

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
                $specService->getActualValue($wheel['tyrewidth'], $car['id']),
                $specService->getActualValue($wheel['tyreseries'], $car['id']),
                $specService->getActualValue($wheel['radius'], $car['id']),
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
