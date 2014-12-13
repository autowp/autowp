<?php

class Application_Form_Moder_Engine_Edit extends Project_Form
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
                array('Engine_Name', 'caption', array (
                    'required'   => true,
                    'decorators' => array('ViewHelper')
                )),
            )
        ));
    }
}