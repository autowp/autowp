<?php

class Application_Form_Forums_Message_New extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'Добавить ответ',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'   => array(
                array('textarea', 'message', array (
                    'required'   => true,
                    'label'      => 'Сообщение',
                    'cols'       => 60,
                    'rows'       => 5,
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        array('StringLength', true, array(0, 1024*4))
                    ),
                    'decorators' => array('ViewHelper'),
                )),
                array('checkbox', 'moderator_attention', array(
                    'label'      => 'Требуется внимание модераторов',
                    'decorators' => array('ViewHelper')
                )),
            )
        ));
    }
}