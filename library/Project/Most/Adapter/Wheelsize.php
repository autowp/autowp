<?php

class Project_Most_Adapter_Wheelsize extends Project_Most_Adapter_Abstract
{
    protected $_attributes;

    protected $_order;

    protected $_attributesTable;

    protected $_equipeItemType;

    public function __construct(array $options)
    {
        parent::__construct($options);

        $this->_attributesTable = new Attrs_Attributes();

        $itemTypes = new Attrs_Item_Types();
        $this->_equipeItemType = $itemTypes->find(2)->current();
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
        $wheel = $this->_attributes['rear'];

        $tyrewidth  = $this->_attributesTable->find($wheel['tyrewidth'])->current();
        $tyrewidthValuesTable = $tyrewidth->getValueTable()->info(Zend_Db_Table_Abstract::NAME);

        $tyreseries = $this->_attributesTable->find($wheel['tyreseries'])->current();
        $tyreseriesValuesTable = $tyreseries->getValueTable()->info(Zend_Db_Table_Abstract::NAME);

        $radius     = $this->_attributesTable->find($wheel['radius'])->current();
        $radiusValuesTable = $radius->getValueTable()->info(Zend_Db_Table_Abstract::NAME);

        $select->join('equipes', 'cars.id=equipes.car_id', null)
            ->join(array('tyrewidth' => $tyrewidthValuesTable), 'equipes.id=tyrewidth.item_id', null)
            ->where('tyrewidth.item_type_id = ?', 2)
            ->where('tyrewidth.attribute_id = ?', $tyrewidth->id)
            ->where('tyrewidth.value > 0')
            ->join(array('tyreseries' => $tyreseriesValuesTable), 'equipes.id=tyreseries.item_id', null)
            ->where('tyreseries.item_type_id = ?', 2)
            ->where('tyreseries.attribute_id = ?', $tyreseries->id)
            ->where('tyreseries.value > 0')
            ->join(array('radius' => $radiusValuesTable), 'equipes.id=radius.item_id', null)
            ->where('radius.item_type_id = ?', 2)
            ->where('radius.attribute_id = ?', $radius->id)
            ->where('radius.value > 0')
            ->group('cars.id')
            ->order(new Zend_Db_Expr('tyrewidth.value*tyreseries.value/100+radius.value*25.4 ' . $this->_order));

        $cars = $select->getTable()->fetchAll($select);

        $result = array();

        foreach ($cars as $car) {

            $result[] = array(
                'car'       =>  $car,
                'valueText' => $this->_getWheelSizeText($car),
            );
        }

        return array(
            'unit' => null,
            'cars' => $result,
        );
    }

    protected function _getWheelSizeText($car)
    {
        $text = array();

        foreach ($this->_attributes as $wheel) {

            $tyrewidth = $this->_attributesTable->find($wheel['tyrewidth'])->current();
            $tyreseries = $this->_attributesTable->find($wheel['tyreseries'])->current();
            $radius = $this->_attributesTable->find($wheel['radius'])->current();
            //$wheel['rimwidth'] = $attributes->find($wheel['rimwidth'])->current();

            foreach ($car->findEquipes() as $equipe) {
                $wheelObj = new Project_WheelSize(
                    $tyrewidth->getActualValue($this->_equipeItemType, $equipe->id),
                    $tyreseries->getActualValue($this->_equipeItemType, $equipe->id),
                    $radius->getActualValue($this->_equipeItemType, $equipe->id),
                    null
                );
                $value = $wheelObj->getTyreName();
                if ($value) {
                    $text[$value] = 0;
                }
            }
        }

        return implode(', ', array_keys($text));
    }
}