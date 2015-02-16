<?php

class Application_Form_Moder_Factory_Edit extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'Двигатель',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements' => array(
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
                array('textarea', 'description', array(
                    'required'    => false,
                    'label'       => 'Описание',
                    'cols'        => 80,
                    'rows'        => 8,
                    'filters'     => array('StringTrim'),
                    'decorators'  => array('ViewHelper'),
                    'class'       => 'span6'
                )),
                array('text', 'lat', array(
                    'required'    => false,
                    'label'       => 'Latitude',
                    'maxlength'   => 20,
                    'size'        => 20,
                    'filters'     => array('StringTrim'),
                    'decorators'  => array('ViewHelper')
                )),
                array('text', 'lng', array(
                    'required'    => false,
                    'label'       => 'Longtitude',
                    'maxlength'   => 20,
                    'size'        => 20,
                    'filters'     => array('StringTrim'),
                    'decorators'  => array('ViewHelper')
                )),
            )
        ));
    }
}