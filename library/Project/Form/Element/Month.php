<?php

class Project_Form_Element_Month extends Zend_Form_Element_Select
{
    /**
     * Element label
     * @var string
     */
    protected $_label = 'Месяц';

    public function __construct($spec, $options = null)
    {
        parent::__construct($spec, $options);

        $multioptions = array (
            ''  =>  '--'
        );

        $date = new Zend_Date(array(
            'year'  =>  2000,
            'month' =>  1,
            'day'   =>  1
        ));
        for ($i=1; $i<=12; $i++) {
            $multioptions[$i] = sprintf('%02d - ', $i) . $date->setMonth($i)->toString('MMMM');
        }

        $this->setMultioptions($multioptions);

        $this->addFilters(array(
            'StringTrim',
            new Filter_IntOrNull
        ));

        $this->addValidators(array(
            'Digits',
            array ('Between', true, array(1, 12))
        ));
    }
}