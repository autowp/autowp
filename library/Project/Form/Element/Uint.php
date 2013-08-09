<?php

class Project_Form_Element_Uint extends Zend_Form_Element_Text
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