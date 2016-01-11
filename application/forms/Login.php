<?php

class Application_Form_Login extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'login/sign-in',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/login.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('User_Login', 'login', array(
                    'required'     => true,
                    'autocomplete' => 'email',
                    'decorators'   => array('ViewHelper'),
                )),
                array('password', 'password', array(
                    'required'   => true,
                    'label'      => 'login/password',
                    'decorators' => array('ViewHelper'),
                )),
                array('checkbox', 'remember', array(
                    'label'      => 'login/remember',
                    'decorators' => array('ViewHelper'),
                )),
            ),
        ));
    }
}