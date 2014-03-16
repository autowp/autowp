<?php

class Application_Form_Moder_Car_Add extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'      => 'post',
            'decorators'  => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'legend'      => 'Новая машина',
            'elements'    => array(
                array('Car_Caption', 'caption', array(
                    'required'   => true,
                    'id'         => 'car_caption',
                    'validators' => array('Car_NameNotExists'),
                    'decorators' => array('ViewHelper')
                )),
                array('Car_Body', 'body', array(
                    'required'   => false,
                    'decorators' => array('ViewHelper')
                )),
                array('year', 'begin_year', array(
                    'required'   => false,
                    'label'      => 'Выпускалась с',
                    'decorators' => array('ViewHelper')
                )),
                array('year', 'end_year', array(
                    'required'   => false,
                    'label'      => 'Выпускалась по',
                    'decorators' => array('ViewHelper')
                )),
                array('Car_Type', 'car_type_id', array(
                    'required'   => false,
                    'decorators' => array('ViewHelper')
                )),
                array('checkbox', 'is_group', array(
                    'required'     => false,
                    'label'        => 'Группа',
                    'decorators'   => array('ViewHelper')
                )),
            )
        ));
    }
}