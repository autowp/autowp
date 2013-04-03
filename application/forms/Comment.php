<?php

class Application_Form_Comment extends Project_Form
{
    public function init()
    {
        parent::init();

        $this->setOptions(array(
            'method'      => 'post',
            'legend'      => 'Добавить комментарий',
            'decorators'  => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/comment.phtml'
                )),
                'Form'
            ),
            'id'          => 'form-add-comment',
            'elements'    => array(
                array('textarea', 'message', array (
                    'required'   => true,
                    'label'      => 'Сообщение',
                    'cols'       => 80,
                    'rows'       => 8,
                    'class'      => 'span5',
                    'filters'    => array('StringTrim'),
                    'decorators' => array('ViewHelper')
                )),
                array('checkbox', 'moderator_attention', array(
                    'label'      => 'Требуется внимание модераторов',
                    'decorators' => array('ViewHelper')
                )),
            )
        ));
    }
}