<?php

class Project_Form_Element_Model_BeginYear extends Project_Form_Element_Year
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'label' => 'Год начала выпуска',
        ));

        $this->addValidators(array(
            array('Between', true, array (1800, (int)date('Y') + 3))
        ));
    }
}