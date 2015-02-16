<?php

class Application_Form_Moder_Picture_Edit extends Project_Form
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
                array('text', 'name', array(
                    'required'    => false,
                    'label'       => 'Особое название',
                    'size'        => 60,
                    'maxlength'   => 255,
                    'filters'     => array('StringTrim', 'SingleSpaces'),
                    'placeholder' => 'Для исключительных ситуаций!',
                    'decorators'  => array('ViewHelper')
                )),
                array('textarea', 'copyrights', array(
                    'required'    => false,
                    'label'       => 'Копирайты',
                    'cols'        => 55,
                    'rows'        => 10,
                    'filters'     => array('StringTrim'),
                    'decorators'  => array('ViewHelper')
                )),
            )
        ));
    }
}