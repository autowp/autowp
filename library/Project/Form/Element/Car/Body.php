<?php

class Project_Form_Element_Car_Body extends Zend_Form_Element_Text
{
    /**
     * Element label
     * @var string
     */
    protected $_label = 'Номер кузова';

    /**
     * @var string
     */
    protected $maxlength = '15';

    /**
     * @var string
     */
    protected $size = '15';

    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

        $this->addFilters(array(
            'StringTrim',
            'SingleSpaces'
        ));
        $this->addValidators(array(
            array('StringLength', true, array(1))
        ));
    }
}