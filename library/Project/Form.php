<?php

class Project_Form extends Zend_Form
{
    /**
     * Constructor
     *
     * Registers form view helper as decorator
     *
     * @param mixed $options
     * @return void
     */
    public function __construct($options = null)
    {
        $this->getPluginLoader(self::DECORATOR)
            ->addPrefixPath('Project_Form_Decorator', 'Project/Form/Decorator');

        $this->getPluginLoader(self::ELEMENT)
            ->addPrefixPath('Project_Form_Element', 'Project/Form/Element');

        $this->addElementPrefixPaths(array(
            array(
                'prefix' => 'Project_Filter',
                'path'   => 'Project/Filter',
                'type'   => Zend_Form_Element::FILTER
            ),
            array(
                'prefix' => 'Project_Decorator',
                'path'   => 'Project/Decorator',
                'type'   => Zend_Form_Element::DECORATOR
            ),
            array(
                'prefix' => 'Project_Validate',
                'path'   => 'Project/Validate',
                'type'   => Zend_Form_Element::VALIDATE
            ),
            array(
                'prefix' => 'Project_Validate_File',
                'path'   => 'Project/Validate/File',
                'type'   => Zend_Form_Element::VALIDATE
            ),
            array(
                'prefix' => 'Autowp_Filter',
                'path'   => 'Autowp/Filter',
                'type'   => Zend_Form_Element::FILTER
            ),
        ));

        parent::__construct($options);
    }
}