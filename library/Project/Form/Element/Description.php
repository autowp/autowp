<?php

class Project_Form_Element_Description extends Zend_Form_Element_Textarea
{
    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

        $this->addFilters(array(
            'StringTrim',
            'SingleSpaces'
        ));
    }
}