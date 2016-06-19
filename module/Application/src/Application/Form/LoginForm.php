<?php

namespace Application\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class LoginForm extends Form
{
    public function __construct()
    {
        parent::__construct();

        /*$this->setOptions(array(
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
        ));*/

        $this->setAttribute('method', 'post');

        $this->add([
            'type'    => 'Text',
            'name'    => 'login',
            'options' => [
                'label' => 'login/email',
            ],
        ]);

        $this->add([
            'type'    => 'Password',
            'name'    => 'password',
            'options' => [
                'label' => 'login/password',
            ],
        ]);

        $this->add([
            'type'    => 'Checkbox',
            'name'    => 'remember',
            'options' => [
                'label' => 'login/remember',
            ],
        ]);
    }
}