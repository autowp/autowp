<?php

require_once 'Zend/Form/Element/Xhtml.php';


class Project_Form_Element_Number extends Zend_Form_Element_Xhtml
{
    /**
     * Default form view helper to use for rendering
     * @var string
     */
    public $helper = 'formNumber';
}
