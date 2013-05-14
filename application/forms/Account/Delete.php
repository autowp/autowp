<?php

class Application_Form_Account_Delete extends Project_Form
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
                array('password', 'password', array(
                    'required'   => true,
                    'label'      => 'Пароль',
                    'size'       => 20,
                    'validators' => array(
                        'NotEmpty',
                    ),
                    'decorators' => array('ViewHelper')
                )),
                array('submit', 'submit', array(
                    'required'   => false,
                    'label'      => 'Удалить учётную запись',
                    'decorators' => array('ViewHelper'),
                    'class'      => 'btn btn-danger'
                )),
            ),
        ));
    }
}