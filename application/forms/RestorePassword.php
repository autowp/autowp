<?php

class Application_Form_RestorePassword extends Project_Form
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
                array('text', 'email', array(
                    'required'   => true,
                    'label'      => 'E-mail',
                    'size'       => 20,
                    'maxlength'  => 50,
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        array('StringLength', true, array(null, 50)),
                        array('EmailAddress', true),
                        'User_EmailExists'
                    ),
                    'decorators' => array(
                        'ViewHelper'
                    ),
                )),
            )
        ));
    }
}