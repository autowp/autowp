<?php

class Project_Most_Adapter_Brakes extends Project_Most_Adapter_Abstract
{
    protected $_attributes;

    protected $_order;

    protected $_attributesTable;

    protected $_carItemType;

    public function __construct(array $options)
    {
        parent::__construct($options);

        $this->_attributesTable = new Attrs_Attributes();

        $itemTypes = new Attrs_Item_Types();
        $this->_carItemType = $itemTypes->find(1)->current();
    }

    public function setAttributes(array $value)
    {
        /*$defaults = array(
            'tyrewidth'  => null,
            'tyreseries' => null,
            'radius'  => null,
            'rimwidth'   => null
        );*/
        $this->_attributes = $value;
    }

    public function setOrder($value)
    {
        $this->_order = $value;
    }

    public function getCars(Zend_Db_Table_Select $select)
    {
        $rear = $this->_attributes['rear'];
        $front = $this->_attributes['front'];

        $wheres = implode($select->getPart( Zend_Db_Select::WHERE ));
        $joins = $select->getPart( Zend_Db_Select::FROM );
        unset($joins['cars']);

        $limit = $this->_most->getCarsCount();

        $selects = array();
        foreach (array($rear, $front) as $axis) {
            $axisSelect = $select->getAdapter()->select()
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

            $diameter  = $this->_attributesTable->find($axis['diameter'])->current();
            $diameterValuesTable = $diameter->getValueTable()->info(Zend_Db_Table_Abstract::NAME);

            $thickness = $this->_attributesTable->find($axis['thickness'])->current();
            $thicknessValuesTable = $thickness->getValueTable()->info(Zend_Db_Table_Abstract::NAME);

            $axisSelect
                ->columns(array('car_id' => 'cars.id', 'size_value' => new Zend_Db_Expr('diameter.value*thickness.value')))
                ->join(array('diameter' => $diameterValuesTable), 'cars.id = diameter.item_id', null)
                ->where('diameter.item_type_id = ?', 1)
                ->where('diameter.attribute_id = ?', $diameter->id)
                ->where('diameter.value > 0')
                ->join(array('thickness' => $thicknessValuesTable), 'cars.id = thickness.item_id', null)
                ->where('thickness.item_type_id = ?', 1)
                ->where('thickness.attribute_id = ?', $thickness->id)
                ->where('thickness.value > 0')
                ->group('cars.id')
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

        //print $select; exit;

        $cars = $select->getTable()->fetchAll($select);

        $result = array();

        foreach ($cars as $car) {

            $result[] = array(
                'car'       => $car,
                'valueHtml' => $this->_getBrakesText($car),
            );
        }

        return array(
            'unit' => null,
            'cars' => $result,
        );
    }

    protected function _getBrakesText($car)
    {
        $text = array();

        $rear = $this->_attributes['rear'];
        $front = $this->_attributes['front'];

        foreach (array($front, $rear) as $axis) {

            $diameter = $this->_attributesTable->find($axis['diameter'])->current();
            $thickness = $this->_attributesTable->find($axis['thickness'])->current();

            $diameterValue = $diameter->getActualValue($this->_carItemType, $car->id);
            $thicknessValue = $thickness->getActualValue($this->_carItemType, $car->id);

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