<?php

namespace Application\Most\Adapter;

use Application\Model\DbTable\Attr;

use Zend_Db_Expr;
use Zend_Db_Select;
use Zend_Db_Table;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

class Acceleration extends AbstractAdapter
{
    protected $attributes;

    protected $order;

    const MPH60_TO_KMH100 = 0.98964381346271110050637609692728;

    public function setAttributes(array $value)
    {
        $this->attributes = $value;

        $this->kmhAttribute = $this->attributeTable->select(['id' => $this->attributes['to100kmh']])->current();
        $this->mphAttribute = $this->attributeTable->select(['id' => $this->attributes['to60mph']])->current();
    }

    public function setOrder($value)
    {
        $this->order = $value;
    }

    public function getCars(Zend_Db_Table_Select $select, $language)
    {
        $axises = [
            [
                'attr' => $this->kmhAttribute,
                'q'    => 1
            ],
            [
                'attr' => $this->mphAttribute,
                'q'    => self::MPH60_TO_KMH100
            ]
        ];

        $wheres = implode($select->getPart(Zend_Db_Select::WHERE));
        $joins = $select->getPart(Zend_Db_Select::FROM);
        unset($joins['cars']);

        $limit = $this->most->getCarsCount();

        $axisBaseSelect = $axisSelect = $select->getAdapter()->select()
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

        $specService = $this->most->getSpecs();

        $selects = [];
        foreach ($axises as $axis) {
            $axisSelect = clone $axisBaseSelect;

            $attr = $axis['attr'];

            $attrValuesTable = $specService->getValueDataTable($attr['type_id'])->info(Zend_Db_Table_Abstract::NAME);

            $valueColumn = $axis['q'] != 1 ? new Zend_Db_Expr('axis.value / ' . $axis['q']) : 'axis.value';

            $axisSelect
                ->columns(['item_id' => 'item.id', 'size_value' => $valueColumn])
                ->join(['axis' => $attrValuesTable], 'item.id = axis.item_id', null)
                ->where('axis.attribute_id = ?', $attr['id'])
                ->where('axis.value > 0')
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
                'valueHtml' => $this->getText($car, $language),
            ];
        }

        return [
            'unit' => null,
            'cars' => $result,
        ];
    }

    protected function getText($car, $language)
    {
        $text = [];

        $axises = [
            [
                'attr' => $this->kmhAttribute,
                'unit' => 'сек до&#xa0;100&#xa0;км/ч'
            ],
            [
                'attr' => $this->mphAttribute,
                'unit' => 'сек до&#xa0;60&#xa0;миль/ч'
            ]
        ];

        $specService = $this->most->getSpecs();

        foreach ($axises as $axis) {
            $value = $specService->getActualValueText($axis['attr']->id, $car->id, $language);

            if ($value > 0) {
                $text[] = $value . ' <span class="unit">' . $axis['unit'] . '</span>';
            }
        }

        return implode("<br />", $text);
    }
}
