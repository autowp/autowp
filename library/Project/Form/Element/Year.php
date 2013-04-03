<?php

class Project_Form_Element_Year extends Zend_Form_Element_Text
{
    /**
     * Element label
     * @var string
     */
    protected $_label = 'Год';

    /**
     * @var string
     */
    protected $maxlength = '4';

    /**
     * @var string
     */
    protected $size = '4';

    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

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