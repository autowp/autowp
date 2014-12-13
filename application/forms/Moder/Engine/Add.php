<?php

class Application_Form_Moder_Engine_Add extends Project_Form
{
    protected $_disableBrand = false;

    public function init()
    {
        parent::init();

        $elements = array(
            array('Engine_Name', 'caption', array (
                'required'   => true,
                'decorators' => array('ViewHelper')
            )),
        );

        if (!$this->_disableBrand) {
            $elements[] = array('Brand', 'brand_id', array (
                'required'   => true,
                'decorators' => array('ViewHelper')
            ));
        }

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'Двигатель',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements' => $elements
        ));
    }

    /**
     * @param bool $value
     * @return Application_Form_Moder_Engine_Add
     */
    public function setDisableBrand($value)
    {
        $this->_disableBrand = (bool)$value;

        return $this;
    }
}