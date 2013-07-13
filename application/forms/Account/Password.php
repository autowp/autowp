<?php

class Application_Form_Account_Password extends Project_Form
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
                array('password', 'password_old', array(
                    'required'   => true,
                    'label'      => 'Текущий',
                    'size'       => 20,
                    'maxlength'  => 20,
                    'validators' => array(
                        'NotEmpty',
                        array('StringLength', true, array(4, 20)),
                    ),
                    'decorators' => array('ViewHelper')
                )),
                array('password', 'password', array(
                    'required'   => true,
                    'label'      => 'Новый',
                    'size'       => 20,
                    'maxlength'  => 20,
                    'validators' => array(
                        'NotEmpty',
                        array('StringLength', true, array(4, 20)),
                    ),
                    'decorators'    => array('ViewHelper')
                )),
                array ('password', 'password_confirm', array (
                    'required'   => true,
                    'label'      => 'Новый (ещё раз)',
                    'size'       => 20,
                    'maxlength'  => 20,
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        array('StringLength', true, array(4, 20)),
                        new My_Validate_PasswordConfirm()
                    ),
                    'decorators' => array('ViewHelper')
                )),
            ),
        ));
    }
}