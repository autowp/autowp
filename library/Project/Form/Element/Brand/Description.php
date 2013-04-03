<?php

class Project_Form_Element_Brand_Description extends Zend_Form_Element_Textarea
{
    const MIN_LENGTH = 2;
    const MAX_LENGTH = 4096;

    /**
     * Element label
     * @var string
     */
    protected $_label = 'Краткое описание';

    /**
     * @var string
     */
    protected $cols = '60';

    /**
     * @var string
     */
    protected $rows = '10';

    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

        $this->addFilters(array(
            'StringTrim',
            'SingleSpaces'
        ));

        $this->addValidators(array(
            array ('StringLength', true, array(0, self::MAX_LENGTH))
        ));
    }
}