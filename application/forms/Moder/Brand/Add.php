<?php

class Application_Form_Moder_Brand_Add extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'Новый Бренд',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'  => array(
                array('text', 'caption', array(
                    'required'     => true,
                    'label'        => 'Название',
                    'size'         => 60,
                    'filters'      => array('StringTrim'),
                    'validators'   => array(new Validate_Brand_Name_Unique),
                    'decorators'   => array('ViewHelper')
                )),
                array('select', 'type_id', array(
                    'required'     => true,
                    'label'        => 'Тип',
                    'multioptions' => array(
                        '1' => 'Производитель',
                        '2' => 'Тюнинг-ателье',
                        '3' => 'Концепт и суперкар'
                    ),
                    'decorators'   => array('ViewHelper')
                )),
            ),
        ));
    }
}