<?php

class Application_Form_Login extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'Вход',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/login.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('User_Login', 'login', array(
                    'required'   => true,
                    'decorators' => array('ViewHelper'),
                )),
                array('password', 'password', array(
                    'required'   => true,
                    'label'      => 'пароль',
                    'decorators' => array('ViewHelper'),
                )),
                array('checkbox', 'remember', array(
                    'label'      => 'запомнить',
                    'decorators' => array('ViewHelper'),
                )),
            ),
        ));
    }
}