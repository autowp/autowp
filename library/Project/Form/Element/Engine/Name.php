<?php

class Project_Form_Element_Engine_Name extends Zend_Form_Element_Text
{
    /**
     * Element label
     * @var string
     */
    protected $_label = 'Название';

    /**
     * @var string
     */
    protected $maxlength = '255';

    /**
     * @var string
     */
    protected $size = '60';

    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

        $this->addFilters(array(
            'StringTrim',
            'SingleSpaces'
        ));
    }
}