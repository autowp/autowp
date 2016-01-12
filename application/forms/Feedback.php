<?php

class Application_Form_Feedback extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'feedback/title',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('text', 'name', array(
                    'label'      => 'feedback/name',
                    'required'   => true,
                    'filters'    => array('StringTrim'),
                    'maxlength'  => 255,
                    'size'       => 80,
                    'decorators' => array('ViewHelper'),
                    'autocomplete' => 'name',
                )),
                array('text', 'email', array(
                    'label'      => 'E-mail',
                    'required'   => false,
                    'filters'    => array('StringTrim'),
                    'validators' => array('EmailAddress'),
                    'maxlength'  => 255,
                    'size'       => 80,
                    'decorators' => array('ViewHelper'),
                    'autocomplete' => 'email',
                )),
                array('textarea', 'message', array(
                    'required'   => true,
                    'label'      => 'feedback/message',
                    'cols'       => 80,
                    'rows'       => 8,
                    'filters'    => array('StringTrim'),
                    'decorators' => array('ViewHelper'),
                )),
                array('captcha', 'captcha', array(
                    'required'   => true,
                    'label'      => 'login/captcha',
                    'captcha'    => array(
                        'captcha' => 'Image',
                        'wordLen' => 4,
                        'timeout' => 300,
                        'font'    => APPLICATION_PATH . '/resources/fonts/arial.ttf',
                        'imgDir'  => PUBLIC_DIR . '/img/captcha/',
                        'imgUrl'  => '/img/captcha/'
                    ),
                    'decorators' => array('Captcha'),
                )),
            )
        ));
    }
}