<?php

class Application_Form_NewPassword extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array('viewScript' => 'forms/bootstrap-horizontal.phtml')),
                'Form'
            ),
            'elements' => array(
                array('password', 'password', array(
                    'required'   => true,
                    'label'      => 'Пароль',
                    'size'       => 20,
                    'maxlength'  => 50,
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        array('StringLength', true, array(6, 50)),
                    ),
                    'decorators' => array('ViewHelper')
                )),
                array('password', 'password_confirm', array(
                    'required'   => true,
                    'label'      => 'Пароль (ещё раз)',
                    'size'       => 20,
                    'maxlength'  => 50,
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        array('StringLength', true, array(6, 50)),
                        'PasswordConfirm'
                    ),
                    'decorators' => array('ViewHelper')
                )),
            )
        ));
    }
}