<?php

class Application_Form_Comment extends Project_Form
{
    private $_canModeratorAttention = false;

    public function setCanModeratorAttention($value)
    {
        $this->_canModeratorAttention = (bool)$value;

        return $this;
    }

    public function init()
    {
        parent::init();

        $elements = array(
            array('textarea', 'message', array (
                'required'   => true,
                'label'      => 'comments/message',
                'cols'       => 80,
                'rows'       => 5,
                'class'      => 'form-control',
                'filters'    => array('StringTrim'),
                'decorators' => array('ViewHelper')
            )),
        );

        if ($this->_canModeratorAttention) {
            $elements[] = array('checkbox', 'moderator_attention', array(
                'label'      => 'comments/it-requires-attention-of-moderators',
                'decorators' => array('ViewHelper')
            ));
        }

        $elements = array_merge($elements, array(
            array('hidden', 'parent_id', array(
                'decorators' => array('ViewHelper')
            )),
            array('hidden', 'resolve', array(
                'decorators' => array('ViewHelper')
            )),
        ));

        $this->setOptions(array(
            'method'     => 'post',
            'legend'     => 'comments/form-title',
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/comment.phtml'
                )),
                'Form'
            ),
            'id'         => 'form-add-comment',
            'elements'   => $elements
        ));
    }
}