<?php

class Application_Form_Moder_Picture_Restore extends Project_Form
{
    public function init()
    {
        $this->setMethod('post');

        $this->addElements(array (
            array ('submit', 'send', array (
                'required' => false,
                'ignore'   => true,
                'class'    => 'btn btn-danger',
                'label'    => 'Восстановить',
            ))
        ));
    }
}