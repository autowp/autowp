<?php

class Application_Form_Registration extends Project_Form
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
            'elements' => array(
                array('text', 'email', array(
                    'required'   => true,
                    'label'      => 'E-mail',
                    'size'       => 20,
                    'maxlength'  => 50,
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        array('StringLength', true, array(null, 50)),
                        'EmailAddress',
                        'User_EmailNotExists'
                    ),
                    'decorators' => array('ViewHelper')
                )),
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
                array('text', 'name', array(
                    'required'   => true,
                    'label'      => 'Имя',
                    'size'       => 20,
                    'maxlength'  => 30,
                    'filters'    => array('StringTrim'),
                    'decorators' => array('ViewHelper')
                )),
                array('captcha', 'captcha', array(
                    'required'       => true,
                    'label'          => 'Введите код защиты',
                    'captcha'        => 'Image',
                    'captchaOptions' => array(
                        'wordLen' => 6,
                        'timeout' => 300,
                        'font'    => RESOURCES_DIR . '/fonts/arial.ttf',
                        'imgDir'  => IMAGES_DIR . '/captcha/',
                        'imgUrl'  => IMAGES_URL . '/captcha/',
                    ),
                    'decorators'     => array('Captcha')
                )),
            )
        ));
    }
}