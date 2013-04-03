<?php

class Project_Form_Element_Model_Name extends Zend_Form_Element_Text
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'maxlength'  => 80,
            'size'       => 60,
            'label'      => 'Название',
        ));

        $this->addFilters(array(
            'StringTrim',
            'SingleSpaces'
        ));

        $this->addValidators(array(
            array('StringLength', true, array (1, 80))
        ));
    }
}