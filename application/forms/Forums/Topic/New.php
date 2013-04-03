<?php

class Application_Form_Forums_Topic_New extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-vertical.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('text', 'name', array(
                    'required'   => true,
                    'label'      => 'Тема топика',
                    'size'       => 80,
                    'maxlength'  => 100,
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        array('StringLength', true, array(0, 100))
                    ),
                    'decorators' => array('ViewHelper'),
                    'class'      => 'span7'
                )),
                array('textarea', 'text', array(
                    'required'   => true,
                    'label'      => 'Сообщение',
                    'cols'       => 140,
                    'rows'       => 15,
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        array('StringLength', true, array(0, 1024*4))
                    ),
                    'decorators' => array('ViewHelper'),
                    'class'      => 'span7'
                )),
                array('checkbox', 'subscribe', array(
                    'required'   => false,
                    'label'      => 'Подписаться на новые сообщения',
                    'decorators' => array('ViewHelper')
                )),
            )
        ));
    }
}