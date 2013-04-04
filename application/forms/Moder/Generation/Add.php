<?php

class Application_Form_Moder_Generation_Add extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('Generation_Shortname', 'shortname', array(
                    'required'   => true,
                    'decorators' => array('ViewHelper')
                )),
                array('Generation_Name', 'name', array(
                    'required'   => true,
                    'decorators' => array('ViewHelper')
                )),
                array('Generation_BeginYear', 'begin_year', array(
                    'required'   => false,
                    'decorators' => array('ViewHelper')
                )),
                array('Generation_EndYear', 'end_year', array(
                    'required'   => false,
                    'decorators' => array('ViewHelper')
                )),
            )
        ));
    }
}