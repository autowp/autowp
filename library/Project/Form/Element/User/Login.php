<?php

class Project_Form_Element_User_Login extends Zend_Form_Element_Text
{
    /**
     * Element label
     * @var string
     */
    protected $_label = 'логин или e-mail';

    /**
     * @var string
     */
    protected $maxlength = '20';

    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

        $this->addFilters(array(
            'StringTrim'
        ));

        $this->addValidators(array(
            new My_Validate_User_Login()
        ));
    }
}