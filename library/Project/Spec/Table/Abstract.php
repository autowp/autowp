<?php

abstract class Project_Spec_Table_Abstract
{
    protected $_attributes = array();

    protected $_renderMap = array(
        85 => array(
            'name' => 'wheel',
            'options'  => array(
                'tyrewidth'  => 87,
                'tyreseries' => 90,
                'radius'     => 88,
                'rimwidth'   => 89
            )
        ),
        86 => array(
            'name' => 'wheel',
            'options'  => array(
                'tyrewidth'  => 91,
                'tyreseries' => 94,
                'radius'     => 92,
                'rimwidth'   => 93
            )
        ),
        19 => array(
            'name' => 'enginePlacement',
            'options'  => array(
                'placement'   => 20,
                'orientation' => 21,
            )
        ),
        60 => array(
            'name' => 'bootVolume',
            'options'  => array(
                'min' => 61,
                'max' => 62,
            )
        ),
        57 => array(
            'name' => 'fuelTank',
            'options'  => array(
                'primary'   => 58,
                'secondary' => 59,
            )
        ),
        24 => array(
            'name' => 'engineConfiguration',
            'options'  => array(
                'cylindersCount'  => 25,
                'cylindersLayout' => 26,
                'valvesCount'     => 27
            )
        ),
        42 => array(
            'name' => 'gearbox',
            'options'  => array(
                'type'  => 43,
                'gears' => 44,
                'name'  => 139,
            )
        ),
    );

    public function preventedRenderSubAttributes($attrId)
    {
        return isset($this->_renderMap[$attrId]);
    }

    public function getAttributes()
    {
        return $this->_attributes;
    }

    protected function _getRenderer($name, $options)
    {
        $className = 'Project_Spec_Table_Value_' . ucfirst($name);
        return new $className($options);
    }

    public function renderValue(Zend_View_Abstract $view, $attribute, $values, $itemTypeId, $itemId)
    {
        $attrId = $attribute['id'];

        $value = isset($values[$attrId]) ? $values[$attrId] : null;

        if (isset($this->_renderMap[$attrId])) {
            $map = $this->_renderMap[$attrId];
            $rendererName = $map['name'];
            $rendererOptions = $map['options'];
        } else {
            $rendererName = 'default';
            $rendererOptions = array();
        }

        $renderer = $this->_getRenderer($rendererName, $rendererOptions);
        return $renderer->render($view, $attribute, $value, $values, $itemTypeId, $itemId);
    }

    abstract public function render(Zend_View_Abstract $view);
}