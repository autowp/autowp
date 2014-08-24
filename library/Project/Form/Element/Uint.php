<?php

class Project_Form_Element_Uint extends Project_Form_Element_Number
{
    /**
     * @var string
     */
    protected $maxlength = '12';

    /**
     * @var string
     */
    protected $size = '12';

    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

        $this->addFilters(array(
            'StringTrim',
            new Project_Filter_IntOrNull
        ));
        $this->addValidators(array(
            'Digits'
        ));
    }
}