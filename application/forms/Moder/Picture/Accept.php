<?php

class Application_Form_Moder_Picture_Accept extends Project_Form
{
    public function init()
    {
        $this->setOptions(array (
            'decorators'    => array(
                'FormElements',
                'PrepareElements',
                'Form',
            ),
            'elements'        => array(
                array('submit', 'send', array (
                    'required'   => false,
                    'ignore'     => true,
                    'label'      => 'Принять',
                    'class'      => 'btn btn-success',
                    'decorators' => array('ViewHelper')
                ))
            )
        ));
    }
}