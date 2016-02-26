<?php

class Application_Form_Moder_Brand_Add_Model extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'Добавить модель',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'  => array(
                array('Model_Name', 'name', array(
                    'required'   => true,
                    'decorators' => array('ViewHelper')
                )),
            )
        ));
    }
}