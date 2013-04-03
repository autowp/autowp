<?php

class Project_Form_Element_Year extends Zend_Form_Element_Text
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'maxlength' => 4,
            'size'      => 4
        ));

        $this->addFilters(array(
            'StringTrim',
            new Filter_IntOrNull
        ));

        $this->addValidators(array(
            'Digits',
            array('Between', true, array(1700, date('Y')+3))
        ));
    }
}