<?php

class Project_Most_Adapter_Acceleration extends Project_Most_Adapter_Abstract
{
    protected $_attributes;

    protected $_order;

    protected $_attributesTable;

    const MPH60_TO_KMH100 = 0.98964381346271110050637609692728;

    public function __construct(array $options)
    {
        $this->_attributesTable = new Attrs_Attributes();

        parent::__construct($options);
    }

    public function setAttributes(array $value)
    {
        $this->_attributes = $value;

        $this->_kmhAttribute =  $this->_attributesTable->find($this->_attributes['to100kmh'])->current();
        $this->_mphAttribute =  $this->_attributesTable->find($this->_attributes['to60mph'])->current();
    }

    public function setOrder($value)
    {
        $this->_order = $value;
    }

    public function getCars(Zend_Db_Table_Select $select)
    {
        $axises = array(
            array(
                'attr' => $this->_kmhAttribute,
                'q'    => 1
            ),
            array(
                'attr' => $this->_mphAttribute,
                'q'    => self::MPH60_TO_KMH100
            )
        );

        $wheres = implode($select->getPart( Zend_Db_Select::WHERE ));
        $joins = $select->getPart( Zend_Db_Select::FROM );
        unset($joins['cars']);

        $limit = $this->_most->getCarsCount();

        $axisBaseSelect = $axisSelect = $select->getAdapter()->select()
            ->from('cars', array());
        if ($wheres) {
            $axisSelect->where($wheres);
        }
        foreach ($joins as $join) {
            if ($join['joinType'] == Zend_Db_Select::INNER_JOIN) {
                $axisSelect->join($join['tableName'], $join['joinCondition'], null, $join['schema']);
            }
        }
        $axisSelect->reset(Zend_Db_Table::COLUMNS);

        $specService = $this->_most->getSpecs();

        $selects = array();
        foreach ($axises as $axis) {
            $axisSelect = clone $axisBaseSelect;

            $attr = $axis['attr'];

            $attrValuesTable = $specService->getValueDataTable($attr->type_id)->info(Zend_Db_Table_Abstract::NAME);

            $valueColumn = $axis['q'] != 1 ? new Zend_Db_Expr('axis.value / ' . $axis['q']) : 'axis.value';

            $axisSelect
                ->columns(array('car_id' => 'cars.id', 'size_value' => $valueColumn))
                ->join(array('axis' => $attrValuesTable), 'cars.id = axis.item_id', null)
                ->where('axis.item_type_id = ?', 1)
                ->where('axis.attribute_id = ?', $attr->id)
                ->where('axis.value > 0')
                ->order('size_value ' . $this->_order)
                ->limit($limit);

            $selects[] = $axisSelect->assemble();
        }

        $select
            ->join(
                array('tbl' => new Zend_Db_Expr('((' . $selects[0] . ') UNION (' . $selects[1] . '))')),
                'cars.id = tbl.car_id',
                null
            )
            ->group('cars.id');


        if ($this->_order == 'asc') {
            $select->order('min(tbl.size_value) ' . $this->_order);
        } else {
            $select->order('max(tbl.size_value) ' . $this->_order);
        }

        $cars = $select->getTable()->fetchAll($select);

        $result = array();

        foreach ($cars as $car) {

            $result[] = array(
                'car'       => $car,
                'valueHtml' => $this->_getText($car),
            );
        }

        return array(
            'unit' => null,
            'cars' => $result,
        );
    }

    protected function _getText($car)
    {
        $text = array();

        $axises = array(
            array(
                'attr' => $this->_kmhAttribute,
                'unit' => 'сек до&#xa0;100&#xa0;км/ч'
            ),
            array(
                'attr' => $this->_mphAttribute,
                'unit' => 'сек до&#xa0;60&#xa0;миль/ч'
            )
        );

        $specService = $this->_most->getSpecs();

        foreach ($axises as $axis) {

            $value = $specService->getActualValueText($axis['attr']->id, 1, $car->id);

            if ($value > 0) {
                $text[] = $value . ' <span class="unit">' . $axis['unit'] . '</span>';
            }
        }

        return implode("<br />", $text);
    }
}