<?php

namespace Application\Most\Adapter;

use Zend_Db_Table_Select;

use Application\Model\DbTable\Attr\Attribute;

use Zend_Db_Expr;
use Zend_Db_Select;
use Zend_Db_Table;
use Zend_Db_Table_Abstract;

class Brakes extends AbstractAdapter
{
    protected $attributes;

    protected $order;

    protected $attributesTable;

    public function __construct(array $options)
    {
        parent::__construct($options);

        $this->attributesTable = new Attribute();
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
        $rear = $this->attributes['rear'];
        $front = $this->attributes['front'];

        $wheres = implode($select->getPart(Zend_Db_Select::WHERE));
        $joins = $select->getPart(Zend_Db_Select::FROM);
        unset($joins['cars']);

        $limit = $this->most->getCarsCount();

        $specService = $this->most->getSpecs();

        $selects = [];
        foreach ([$rear, $front] as $axis) {
            $axisSelect = $select->getAdapter()->select()
                ->from('item', []);
            if ($wheres) {
                $axisSelect->where($wheres);
            }
            foreach ($joins as $join) {
                if ($join['joinType'] == Zend_Db_Select::INNER_JOIN) {
                    $axisSelect->join($join['tableName'], $join['joinCondition'], null, $join['schema']);
                }
            }
            $axisSelect->reset(Zend_Db_Table::COLUMNS);

            $diameter  = $this->attributesTable->find($axis['diameter'])->current();
            $diameterValuesTable = $specService->getValueDataTable($diameter->type_id)
                ->info(Zend_Db_Table_Abstract::NAME);

            $thickness = $this->attributesTable->find($axis['thickness'])->current();
            $thicknessValuesTable = $specService->getValueDataTable($thickness->type_id)
                ->info(Zend_Db_Table_Abstract::NAME);

            $axisSelect
                ->columns(['item_id' => 'item.id', 'size_value' => new Zend_Db_Expr('diameter.value*thickness.value')])
                ->join(['diameter' => $diameterValuesTable], 'item.id = diameter.item_id', null)
                ->where('diameter.attribute_id = ?', $diameter->id)
                ->where('diameter.value > 0')
                ->join(['thickness' => $thicknessValuesTable], 'item.id = thickness.item_id', null)
                ->where('thickness.attribute_id = ?', $thickness->id)
                ->where('thickness.value > 0')
                ->group('item.id')
                ->order('size_value ' . $this->order)
                ->limit($limit);

            $selects[] = $axisSelect->assemble();
        }

        $select
            ->join(
                ['tbl' => new Zend_Db_Expr('((' . $selects[0] . ') UNION (' . $selects[1] . '))')],
                'item.id = tbl.item_id',
                null
            )
            ->group('item.id');


        if ($this->order == 'asc') {
            $select->order('min(tbl.size_value) ' . $this->order);
        } else {
            $select->order('max(tbl.size_value) ' . $this->order);
        }

        $cars = $select->getTable()->fetchAll($select);

        $result = [];

        foreach ($cars as $car) {
            $result[] = [
                'car'       => $car,
                'valueHtml' => $this->getBrakesText($car),
            ];
        }

        return [
            'unit' => null,
            'cars' => $result,
        ];
    }

    protected function getBrakesText($car)
    {
        $text = [];

        $rear = $this->attributes['rear'];
        $front = $this->attributes['front'];

        $specService = $this->most->getSpecs();

        foreach ([$front, $rear] as $axis) {
            $diameterValue = $specService->getActualValue($axis['diameter'], $car->id);
            $thicknessValue = $specService->getActualValue($axis['thickness'], $car->id);

            if ($diameterValue || $thicknessValue) {
                $value = $diameterValue . ' × ' . $thicknessValue . ' <span class="unit">мм</span>';

                if ($value) {
                    $text[$value] = 0;
                }
            }
        }

        return implode("<br />", array_keys($text));
    }
}
