<?php

class Project_Form_Element_Model_Name extends Zend_Form_Element_Text
{
    public function init()
    {
        $this->setOptions(array(
            'maxlength'  => 80,
            'size'       => 60,
            'label'      => 'Название',
            'filters'    => array(
                'StringTrim', 'SingleSpaces'
            ),
            'validators' => array(
                array('StringLength', true, array (1, 80))
            )
        ));

        parent::init();
    }
}