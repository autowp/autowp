<?php

class Project_Form_Element_Generation_BeginYear extends Project_Form_Element_Year
{
    /**
     * Element label
     * @var string
     */
    protected $_label = 'Год начала выпуска';

    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

        $this->addValidators(array(
            array('Between', true, array (1800, (int)date('Y') + 3))
        ));
    }
}