<?php

class Application_Form_Moder_Picture_Vote extends Project_Form
{
    public function init()
    {
        $this->setOptions(array(
            'decorators' => array(
                'PrepareElements',
                'FormElements',
                'Form'
            ),
            'class' => 'form-inline',
            'elements'   => array(
                array('text', 'reason', array(
                    'required'   => true,
                    'label'      => 'Причина',
                    'size'       => 60,
                    'maxlength'  => 255,
                    'filters'    => array('StringTrim'),
                    'class'      => 'form-control',
                    'decorators' => array(
                        array('ViewScript', array(
                            'viewScript' => 'element/reason.phtml'
                        ))
                    )
                )),
                array('submit', 'vote', array(
                    'required'   => false,
                    'ignore'     => false,
                    'decorators' => array(
                        array('ViewScript', array(
                            'viewScript' => 'element/moder_vote.phtml'
                        ))
                    )
                ))
            )
        ));

    }
}