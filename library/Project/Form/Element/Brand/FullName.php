<?php

class Project_Form_Element_Brand_FullName extends Zend_Form_Element_Text
{
    const MIN_LENGTH = 2;
    const MAX_LENGTH = 255;

    /**
     * Element label
     * @var string
     */
    protected $_label = 'Полное название';

    /**
     * @var string
     */
    protected $maxlength = self::MAX_LENGTH;

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

        $this->addValidators(array(
            array('StringLength', true, array(self::MIN_LENGTH, self::MAX_LENGTH))
        ));
    }
}