<?php

class Application_Form_Moder_Twins_Group_Edit extends Project_Form
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
                    'required'   => true,
                    'label'      => 'Название',
                    'maxlength'  => 255,
                    'size'       => 80,
                    'filters'    => array(
                        'StringTrim', 'SingleSpaces'
                    ),
                    'validators' => array(
                        array('StringLength', true, array(1, 255))
                    ),
                    'decorators' => array('ViewHelper')
                )),
                array('textarea', 'description', array(
                    'required'   => false,
                    'label'      => 'Описание',
                    'rows'       => 10,
                    'cols'       => 80,
                    'filters'    => array('StringTrim'),
                    'validators' =>  array(
                        array('StringLength', true, array(0, 4096))
                    ),
                    'decorators' => array('ViewHelper')
                )),
            )
        ));
    }
}