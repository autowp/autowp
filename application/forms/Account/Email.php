<?php

class Application_Form_Account_Email extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     =>    'post',
            'decorators' =>    array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements' => array(
                array('text', 'e_mail', array(
                    'required'   => true,
                    'label'      => 'E-mail',
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        'EmailAddress',
                        new Validate_User_Email_Not_Exists()
                    ),
                    'maxlength'  => 50,
                    'size'       => 30,
                    'decorators' => array('ViewHelper'),
                )),
            ),
        ));
    }
}