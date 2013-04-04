<?php

class Project_Form_Element_Generation_Shortname extends Zend_Form_Element_Text
{
    /**
     * Element label
     * @var string
     */
    protected $_label = 'Короткое название';

    /**
     * @var string
     */
    protected $maxlength = '20';

    /**
     * @var string
     */
    protected $size = '20';

    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

        $this->addFilters(array(
            'StringTrim',
            'SingleSpaces'
        ));

        $this->addValidators(array(
            array('StringLength', true, array (1, 20))
        ));
    }
}