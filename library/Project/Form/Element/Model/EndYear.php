<?php

class Project_Form_Element_Model_EndYear extends Project_Form_Element_Year
{
    public function init()
    {
        parent::init();

        $this->addValidators(array(
            array('Between', true, array (1800, (int)date('Y') + 3))
        ));

        $this->setOptions(array(
            'label' => 'Год окончания выпуска'
        ));
    }
}