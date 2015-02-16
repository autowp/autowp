<?php

class Application_Form_Moder_Factory_Add extends Project_Form
{
    public function init()
    {
        parent::init();

        $elements = array(
            array('Factory_Name', 'name', array (
                'required'   => true,
                'decorators' => array('ViewHelper')
            )),
            array('year', 'year_from', array(
                'required'    => false,
                'label'       => 'Год с',
                'placeholder' => 'с',
                'decorators'  => array('ViewHelper')
            )),
            array('year', 'year_to', array(
                'required'    => false,
                'label'       => 'Год по',
                'placeholder' => 'по',
                'decorators'  => array('ViewHelper')
            )),
        );

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'Завод',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements' => $elements
        ));
    }
}